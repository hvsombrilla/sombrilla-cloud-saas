<?php

namespace SombrillaCloud\Services;

use Studio\Auth\SignedLogin;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Support\Mailer;
use SombrillaCloud\Support\Tenancy;

/**
 * Ciclo de vida de una instancia de Sombrilla Studio. Cada método devuelve ['ok' => bool, 'error' => ?string]
 * y deja constancia en scloud_logs; nunca lanza excepciones.
 */
class Provisioner
{
    /** Crea la instancia (SQLite + storage + administrador) y, si aplica, conecta o compra el dominio. */
    public static function create(Service $service, bool $notify = true): array
    {
        $service->loadMissing('plan', 'customer');

        if ($service->status === 'active' && $service->provisioned_at) {
            return ['ok' => false, 'error' => 'El servicio ya está activo.'];
        }
        $customer = $service->customer;
        if (!$customer) {
            return self::fail($service, 'create', 'El servicio no tiene cliente.');
        }
        if (trim((string) $service->workspace_name) === '') {
            return self::fail($service, 'create', 'El servicio no tiene nombre de workspace.');
        }

        try {
            Tenancy::secret();
            $slug = $service->subdomain ?: DomainService::generateSlug((string) $service->workspace_name);
            if ($error = DomainService::subdomainError($slug, $service->id)) {
                return self::fail($service, 'create', $error);
            }
        } catch (\Throwable $e) {
            return self::fail($service, 'create', $e->getMessage());
        }

        $password = self::password();
        $email    = strtolower((string) $customer->email);
        $limits   = $service->plan ? PlanLimits::forPlan($service->plan) : [];
        try {
            TenantDb::create($slug, (string) $service->workspace_name, (string) $customer->name, $email, $password, $limits);
            PlanLimits::write($slug, $limits);
        } catch (\Throwable $e) {
            TenantDb::destroy($slug);

            return self::fail($service, 'create', 'No se pudo crear la instancia: ' . $e->getMessage());
        }

        $service->fill([
            'subdomain'      => $slug,
            'admin_email'    => $email,
            'status'         => 'active',
            'last_error'     => null,
            'provisioned_at' => now(),
        ]);
        if (!$service->registration_date) {
            $service->registration_date = date('Y-m-d');
        }
        $service->save();
        ServiceLog::write($service->id, 'create', true, 'Instancia ' . Tenancy::host($slug) . ' creada. Administrador: ' . $email . '.', $service->customer_id);

        self::connectDomain($service);
        do_action('scloud_service_created', ['service_id' => $service->id]);

        if ($notify) {
            $sent = Mailer::instanceReady($service->fresh(['plan', 'customer']), $password);
            if (!$sent) {
                ServiceLog::write($service->id, 'welcome_mail', false, 'No se pudo enviar el correo de bienvenida. Usa «Restablecer acceso» para generar y enviar una contraseña nueva.', $service->customer_id);
            }
        }

        return ['ok' => true, 'error' => null, 'password' => $password];
    }

    /** Dominio propio: se registra el mapeo y se espera el CNAME. Dominio comprado: se registra en Cloudflare y se apunta el DNS. */
    private static function connectDomain(Service $service): void
    {
        if (!$service->domain || $service->domain_mode === 'subdomain') {
            return;
        }
        Tenancy::mapDomain($service->domain, $service->subdomain);

        if ($service->domain_mode === 'own') {
            $service->update(['domain_status' => 'dns_pending']);
            DomainService::sync($service);

            return;
        }

        if (!Cloudflare::enabled()) {
            $service->update(['domain_status' => 'failed']);
            DomainService::alert($service, "No se puede comprar {$service->domain}: la venta de dominios está desactivada o sin configurar. La instancia funciona en " . Tenancy::host($service->subdomain) . '.');

            return;
        }
        try {
            $check = Cloudflare::checkOne($service->domain);
            if (!$check || !$check['registrable']) {
                throw new CloudflareException('El dominio ya no está disponible.');
            }
            $reg = Cloudflare::register($service->domain);
            $service->update([
                'domain_status'   => $reg['completed'] ? 'dns_pending' : 'registering',
                'domain_cost_usd' => $check['cost'],
            ]);
            ServiceLog::write($service->id, 'domain_register', true, "Registro de {$service->domain} solicitado a Cloudflare (estado: {$reg['state']}).", $service->customer_id);
            DomainService::sync($service->fresh());
        } catch (CloudflareException $e) {
            $service->update(['domain_status' => 'failed']);
            DomainService::alert($service, "No se pudo comprar {$service->domain}: " . $e->getMessage() . ' La instancia funciona en ' . Tenancy::host($service->subdomain) . '.');
        }
    }

    public static function suspend(Service $service, string $reason = ''): array
    {
        if ($service->subdomain) {
            Tenancy::setSuspended($service->subdomain, true);
        }
        $service->update(['status' => 'suspended', 'suspend_reason' => $reason ?: null]);
        ServiceLog::write($service->id, 'suspend', true, 'Instancia suspendida' . ($reason ? ": {$reason}" : '.'), $service->customer_id);
        do_action('scloud_service_suspended', ['service_id' => $service->id, 'reason' => $reason]);

        return ['ok' => true, 'error' => null];
    }

    public static function unsuspend(Service $service): array
    {
        if ($service->subdomain) {
            Tenancy::setSuspended($service->subdomain, false);
        }
        $service->update(['status' => 'active', 'suspend_reason' => null]);
        ServiceLog::write($service->id, 'unsuspend', true, 'Instancia reactivada.', $service->customer_id);

        return ['ok' => true, 'error' => null];
    }

    /** Archiva la instancia en data/trash (recuperable a mano) y libera el subdominio y el dominio. */
    public static function terminate(Service $service): array
    {
        if ($service->subdomain && Tenancy::exists($service->subdomain) && !TenantDb::archive($service->subdomain)) {
            return self::fail($service, 'terminate', 'No se pudo archivar la instancia (permisos de storage).', false);
        }
        if ($service->domain) {
            Tenancy::unmapDomain($service->domain);
        }
        $service->update(['status' => 'terminated']);
        ServiceLog::write($service->id, 'terminate', true, 'Instancia archivada en data/trash.', $service->customer_id);

        return ['ok' => true, 'error' => null];
    }

    /** Nueva contraseña para el administrador de la instancia; se envía por correo al cliente (no se guarda). */
    public static function resetAdminPassword(Service $service): array
    {
        $service->loadMissing('customer', 'plan');
        if (!$service->subdomain || !$service->admin_email || !Tenancy::exists($service->subdomain)) {
            return ['ok' => false, 'error' => 'El servicio no tiene una instancia creada.'];
        }
        $password = self::password();
        try {
            if (!TenantDb::setAdminPassword($service->subdomain, $service->admin_email, $password)) {
                return self::fail($service, 'reset_access', 'No se encontró el usuario administrador en la instancia.', false);
            }
        } catch (\Throwable $e) {
            return self::fail($service, 'reset_access', $e->getMessage(), false);
        }
        $sent = Mailer::instanceReady($service, $password, true);
        ServiceLog::write($service->id, 'reset_access', true, 'Contraseña del administrador restablecida' . ($sent ? ' y enviada al cliente.' : ' (el correo no pudo enviarse).'), $service->customer_id);

        return ['ok' => true, 'error' => null, 'password' => $password, 'sent' => $sent];
    }

    /** URL que abre la instancia ya autenticada como su administrador (token firmado de un solo uso, 60 s). */
    public static function loginUrl(Service $service): array
    {
        if ($service->status !== 'active' || !$service->subdomain || !$service->admin_email) {
            return ['ok' => false, 'error' => 'La instancia no está activa.', 'url' => null];
        }
        if (!class_exists(SignedLogin::class) || !SignedLogin::enabled()) {
            return ['ok' => false, 'error' => 'El acceso directo no está configurado (scloud.tenant_secret).', 'url' => null];
        }
        $token = SignedLogin::make($service->admin_email, Tenancy::host($service->subdomain), 60);

        return ['ok' => true, 'error' => null, 'url' => Tenancy::url($service->subdomain) . '/studio/sso?t=' . $token];
    }

    /** Contraseña fuerte, legible (sin caracteres ambiguos). */
    public static function password(int $length = 16): string
    {
        $sets  = ['abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!@#%^*-_=+'];
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        $all = implode('', $sets);
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

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
                ss_notify('permission:scloud_manage_services', 'Error con la instancia: ' . $service->title(), [
                    'type' => 'error', 'body' => $error, 'url' => url('/scloud/servicios/' . $service->id),
                    'icon' => 'ph ph-cloud-warning', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }

        return ['ok' => false, 'error' => $error];
    }
}
