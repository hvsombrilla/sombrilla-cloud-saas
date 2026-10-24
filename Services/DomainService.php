<?php

namespace SombrillaCloud\Services;

use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Support\Tenancy;

/** Subdominios, dominios propios o comprados, y su conexión (CNAME) con la instancia. */
final class DomainService
{
    public const MODES = ['subdomain', 'buy', 'own'];

    /** Estados que ocupan un subdominio o dominio. */
    private const LIVE = ['pending', 'active', 'suspended', 'failed'];

    public static function modeEnabled(string $mode): bool
    {
        return match ($mode) {
            'subdomain' => true,
            'buy'       => Cloudflare::enabled(),
            'own'       => (bool) Tenancy::cfg('domains.own_enabled', true),
            default     => false,
        };
    }

    public static function enabledModes(): array
    {
        return array_values(array_filter(self::MODES, fn($m) => self::modeEnabled($m)));
    }

    /** Motivo por el que el subdominio no se puede usar (formato, reservado u ocupado), o null si está libre. */
    public static function subdomainError(string $slug, ?int $ignoreServiceId = null): ?string
    {
        $slug = strtolower(trim($slug));
        if ($error = Tenancy::slugError($slug)) {
            return $error;
        }
        if (!Tenancy::configured()) {
            error_log('[sombrilla-cloud] Falta scloud.tenant_secret en ss-config.php: no se pueden contratar instancias.');

            return 'La contratación no está disponible en este momento. Inténtalo más tarde o contacta con soporte.';
        }
        $taken = Service::where('subdomain', $slug)->whereIn('status', self::LIVE)
            ->when($ignoreServiceId, fn($q) => $q->where('id', '!=', $ignoreServiceId))->exists();
        if ($taken || Tenancy::exists($slug)) {
            return 'Ese subdominio ya está en uso. Prueba con otro.';
        }

        return null;
    }

    /** Subdominio libre generado a partir del nombre del workspace (para las opciones de dominio propio o comprado). */
    public static function generateSlug(string $workspace): string
    {
        $base = Tenancy::slugify($workspace);
        $base = substr($base, 0, 24);
        $slug = $base;
        for ($i = 0; $i < 25 && self::subdomainError($slug) !== null; $i++) {
            $slug = rtrim(substr($base, 0, 24), '-') . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
        }

        return $slug;
    }

    /** Motivo por el que un dominio propio/comprado no puede usarse, o null. */
    public static function domainError(?string $domain, ?int $ignoreServiceId = null): ?string
    {
        if ($domain === null) {
            return 'Escribe un dominio válido (por ejemplo: miempresa.com).';
        }
        $base = Tenancy::baseDomain();
        if ($domain === $base || str_ends_with($domain, '.' . $base)) {
            return 'Para usar un subdominio de ' . $base . ' elige la opción «Subdominio».';
        }
        $taken = Service::where('domain', $domain)->whereIn('status', self::LIVE)
            ->when($ignoreServiceId, fn($q) => $q->where('id', '!=', $ignoreServiceId))->exists()
            || Tenancy::slugForDomain($domain) !== null;

        return $taken ? "El dominio {$domain} ya está conectado a otra instancia." : null;
    }

    /** Instrucciones DNS para apuntar un dominio propio a la instancia. */
    public static function dnsInstructions(string $slug, ?string $domain = null): array
    {
        $target = Tenancy::host($slug);
        $ip     = (string) Tenancy::cfg('server_ip', '');

        return [
            'target'  => $target,
            'ip'      => $ip !== '' ? $ip : null,
            'records' => array_values(array_filter([
                ['type' => 'CNAME', 'name' => 'www', 'value' => $target, 'note' => 'Para www.' . ($domain ?: 'tudominio.com')],
                ['type' => 'CNAME', 'name' => '@', 'value' => $target, 'note' => 'Dominio raíz: si tu proveedor no permite CNAME en la raíz, usa ALIAS/ANAME o CNAME flattening (Cloudflare lo hace solo)' . ($ip !== '' ? ', o un registro A.' : '.')],
                $ip !== '' ? ['type' => 'A', 'name' => '@', 'value' => $ip, 'note' => 'Alternativa para el dominio raíz'] : null,
            ])),
        ];
    }

    /** ¿El dominio ya apunta a la instancia? Compara por CNAME o por las IP resueltas. */
    public static function dnsPointsToInstance(string $domain, string $slug): bool
    {
        $target = Tenancy::host($slug);
        foreach ([$domain, 'www.' . $domain] as $name) {
            foreach (@dns_get_record($name, DNS_CNAME) ?: [] as $r) {
                if (rtrim(strtolower((string) ($r['target'] ?? '')), '.') === $target) {
                    return true;
                }
            }
        }
        $mine  = @gethostbynamel($target) ?: [];
        $theirs = @gethostbynamel($domain) ?: [];
        $ip    = (string) Tenancy::cfg('server_ip', '');
        if ($ip !== '') {
            $mine[] = $ip;
        }

        return (bool) array_intersect($mine, $theirs);
    }

    /**
     * Avanza el estado del dominio de un servicio: termina el registro, apunta el DNS (dominios comprados)
     * o comprueba el CNAME (dominios propios). Idempotente; lo llama el cron y el botón «Verificar».
     */
    public static function sync(Service $service): void
    {
        if (!$service->subdomain || !$service->domain || !in_array($service->domain_status, ['registering', 'dns_pending'], true)) {
            return;
        }

        try {
            if ($service->domain_mode === 'buy') {
                if ($service->domain_status === 'registering') {
                    $st = Cloudflare::registrationStatus($service->domain);
                    if (in_array($st['state'], ['failed', 'blocked'], true)) {
                        $service->update(['domain_status' => 'failed', 'last_error' => 'Registro del dominio: ' . ($st['error'] ?: $st['state'])]);
                        self::alert($service, 'No se pudo registrar el dominio ' . $service->domain . ': ' . ($st['error'] ?: $st['state']));

                        return;
                    }
                    if ($st['state'] === 'action_required') {
                        self::alert($service, 'El registro de ' . $service->domain . ' requiere una acción en el panel de Cloudflare.');

                        return;
                    }
                    if (!$st['completed']) {
                        return;
                    }
                    $service->domain_status = 'dns_pending';
                    if (!empty($st['expires_at'])) {
                        $service->domain_expires_at = substr((string) $st['expires_at'], 0, 10);
                    }
                    $service->save();
                    ServiceLog::write($service->id, 'domain_registered', true, "Dominio {$service->domain} registrado.", $service->customer_id);
                }
                Cloudflare::pointTo($service->domain, Tenancy::host($service->subdomain));
                $service->update(['domain_status' => 'connected']);
                ServiceLog::write($service->id, 'domain_dns', true, "DNS de {$service->domain} apuntando a " . Tenancy::host($service->subdomain) . '.', $service->customer_id);

                return;
            }

            if (self::dnsPointsToInstance($service->domain, $service->subdomain)) {
                $service->update(['domain_status' => 'connected']);
                ServiceLog::write($service->id, 'domain_dns', true, "{$service->domain} ya apunta a la instancia.", $service->customer_id);
            }
        } catch (CloudflareException $e) {
            // La zona puede tardar unos minutos en existir: se reintenta en el siguiente ciclo.
            $service->update(['last_error' => $e->getMessage()]);
        }
    }

    public static function alert(Service $service, string $message): void
    {
        ServiceLog::write($service->id, 'domain', false, $message, $service->customer_id);
        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_manage_services', 'Dominio: ' . $service->title(), [
                    'type' => 'error', 'body' => $message, 'url' => url('/scloud/servicios/' . $service->id),
                    'icon' => 'ph ph-globe', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }
    }
}
