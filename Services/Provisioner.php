<?php

namespace SombrillaCloud\Services;

use SombrillaCloud\Models\Server;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Support\Mailer;
use SombrillaCloud\Support\Settings;

/**
 * Acciones sobre la cuenta cPanel de un servicio. Cada método devuelve ['ok' => bool, 'error' => ?string]
 * y deja constancia en scloud_logs; nunca lanza excepciones.
 */
class Provisioner
{
    /** Crea la cuenta en WHM con el paquete del plan. */
    public static function create(Service $service, bool $notify = true): array
    {
        $service->loadMissing('plan', 'customer');

        if ($service->status === 'active' && $service->username) {
            return ['ok' => false, 'error' => 'El servicio ya está activo.'];
        }

        $server = $service->server ?: self::pickServer($service->plan?->server_id);
        if (!$server) {
            return self::fail($service, 'create', 'No hay un servidor WHM activo con capacidad disponible.');
        }
        $package = $service->whm_package ?: ($service->plan->whm_package ?? '');
        if ($package === '') {
            return self::fail($service, 'create', 'El plan no tiene un paquete de WHM asignado.');
        }
        if (!$service->domain) {
            return self::fail($service, 'create', 'El servicio no tiene dominio.');
        }

        $whm      = Whm::for($server);
        $password = $service->plainPassword() ?: self::password();
        $username = $service->username ?: self::username((string) $service->domain);
        $lastError = '';

        // Si el usuario ya existe en WHM se reintenta con otro.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $whm->createAccount($username, (string) $service->domain, $password, $package, (string) $service->customer->email);
                $lastError = '';
                break;
            } catch (WhmException $e) {
                $lastError = $e->getMessage();
                if (!preg_match('/(user|usuario|username).*(exist|taken|reserved|in use)|exist.*(user|usuario)/i', $lastError)) {
                    break;
                }
                $username = self::username((string) $service->domain, true);
            }
        }

        if ($lastError !== '') {
            $service->server_id = $server->id;
            $service->save();

            return self::fail($service, 'create', $lastError);
        }

        $service->fill([
            'server_id'   => $server->id,
            'username'    => $username,
            'whm_package' => $package,
            'status'      => 'active',
            'last_error'  => null,
        ]);
        $service->setPlainPassword($password);
        if (!$service->registration_date) {
            $service->registration_date = date('Y-m-d');
        }
        $service->save();

        ServiceLog::write($service->id, 'create', true, "Cuenta {$username} creada en {$server->name} con el paquete {$package}.", $service->customer_id);
        do_action('scloud_service_created', ['service_id' => $service->id]);

        if ($notify) {
            Mailer::accountCreated($service->fresh(['plan', 'customer', 'server']));
        }

        return ['ok' => true, 'error' => null];
    }

    public static function suspend(Service $service, string $reason = ''): array
    {
        if (!$service->username || !$service->server) {
            $service->update(['status' => 'suspended', 'suspend_reason' => $reason ?: null]);

            return ['ok' => true, 'error' => null];
        }
        try {
            Whm::for($service->server)->suspend($service->username, $reason);
        } catch (WhmException $e) {
            return self::fail($service, 'suspend', $e->getMessage(), false);
        }
        $service->update(['status' => 'suspended', 'suspend_reason' => $reason ?: null]);
        ServiceLog::write($service->id, 'suspend', true, 'Cuenta suspendida' . ($reason ? ": {$reason}" : '.'), $service->customer_id);
        do_action('scloud_service_suspended', ['service_id' => $service->id, 'reason' => $reason]);

        return ['ok' => true, 'error' => null];
    }

    public static function unsuspend(Service $service): array
    {
        if ($service->username && $service->server) {
            try {
                Whm::for($service->server)->unsuspend($service->username);
            } catch (WhmException $e) {
                return self::fail($service, 'unsuspend', $e->getMessage(), false);
            }
        }
        $service->update(['status' => 'active', 'suspend_reason' => null]);
        ServiceLog::write($service->id, 'unsuspend', true, 'Cuenta reactivada.', $service->customer_id);

        return ['ok' => true, 'error' => null];
    }

    public static function terminate(Service $service): array
    {
        if ($service->username && $service->server) {
            try {
                Whm::for($service->server)->terminate($service->username);
            } catch (WhmException $e) {
                return self::fail($service, 'terminate', $e->getMessage(), false);
            }
        }
        $service->update(['status' => 'terminated']);
        ServiceLog::write($service->id, 'terminate', true, 'Cuenta eliminada del servidor.', $service->customer_id);

        return ['ok' => true, 'error' => null];
    }

    public static function changePackage(Service $service, string $package): array
    {
        if (!$service->username || !$service->server) {
            return ['ok' => false, 'error' => 'El servicio no tiene una cuenta creada.'];
        }
        try {
            Whm::for($service->server)->changePackage($service->username, $package);
        } catch (WhmException $e) {
            return self::fail($service, 'change_package', $e->getMessage(), false);
        }
        $service->update(['whm_package' => $package]);
        ServiceLog::write($service->id, 'change_package', true, "Paquete cambiado a {$package}.", $service->customer_id);

        return ['ok' => true, 'error' => null];
    }

    public static function changePassword(Service $service, string $password): array
    {
        if (!$service->username || !$service->server) {
            return ['ok' => false, 'error' => 'El servicio no tiene una cuenta creada.'];
        }
        try {
            Whm::for($service->server)->changePassword($service->username, $password);
        } catch (WhmException $e) {
            return self::fail($service, 'change_password', $e->getMessage(), false);
        }
        $service->setPlainPassword($password);
        $service->save();
        ServiceLog::write($service->id, 'change_password', true, 'Contraseña de cPanel cambiada.', $service->customer_id);

        return ['ok' => true, 'error' => null];
    }

    /** URL de inicio de sesión directo en cPanel. */
    public static function cpanelUrl(Service $service): array
    {
        if ($service->status !== 'active' || !$service->username || !$service->server) {
            return ['ok' => false, 'error' => 'La cuenta no está activa.', 'url' => null];
        }
        try {
            return ['ok' => true, 'error' => null, 'url' => Whm::for($service->server)->cpanelSession($service->username)];
        } catch (WhmException $e) {
            ServiceLog::write($service->id, 'cpanel_login', false, $e->getMessage(), $service->customer_id);

            return ['ok' => false, 'error' => 'No se pudo abrir cPanel en este momento.', 'url' => null];
        }
    }

    /** Servidor del plan si está activo y con espacio; si no, el activo con menos cuentas. */
    public static function pickServer(?int $preferredId = null): ?Server
    {
        if ($preferredId) {
            $server = Server::where('id', $preferredId)->where('is_active', true)->first();
            if ($server && !$server->isFull()) {
                return $server;
            }
        }

        return Server::where('is_active', true)->get()
            ->reject(fn(Server $s) => $s->isFull() || !$s->hasToken())
            ->sortBy(fn(Server $s) => $s->accountCount())
            ->first();
    }

    /** Usuario de cPanel a partir del dominio: empieza por letra, solo [a-z0-9], único en la base de datos. */
    public static function username(string $domain, bool $random = false): string
    {
        $len  = max(5, min(16, Settings::int('username_length') ?: 8));
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', preg_replace('/^www\./i', '', $domain))[0] ?? ''));
        $base = ltrim((string) $base, '0123456789');
        if (str_starts_with($base, 'test')) {
            $base = 'u' . $base; // WHM reserva los usuarios que empiezan por "test"
        }
        if ($base === '') {
            $base = 'sc';
        }

        $candidate = $random ? substr($base, 0, $len - 3) . random_int(100, 999) : substr($base, 0, $len);
        $i = 1;
        while (Service::where('username', $candidate)->exists()) {
            $suffix    = (string) $i++;
            $candidate = substr($base, 0, $len - strlen($suffix)) . $suffix;
        }

        return $candidate;
    }

    /** Contraseña fuerte (WHM exige una puntuación mínima). */
    public static function password(int $length = 16): string
    {
        $sets = ['abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!@#%^*-_=+'];
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        $all = implode('', $sets);
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }

    private static function fail(Service $service, string $action, string $error, bool $markFailed = true): array
    {
        if ($markFailed) {
            $service->status     = 'failed';
            $service->last_error = $error;
            $service->save();
        } else {
            $service->update(['last_error' => $error]);
        }
        ServiceLog::write($service->id, $action, false, $error, $service->customer_id);

        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_manage_services', 'Error en WHM: ' . $service->title(), [
                    'type' => 'error', 'body' => $error, 'url' => url('/scloud/servicios/' . $service->id),
                    'icon' => 'fa fa-server', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }

        return ['ok' => false, 'error' => $error];
    }
}
