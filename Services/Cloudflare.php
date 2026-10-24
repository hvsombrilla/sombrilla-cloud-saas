<?php

namespace SombrillaCloud\Services;

use SombrillaCloud\Support\Tenancy;

/**
 * Cliente mínimo de la API de Cloudflare para vender dominios (Registrar API) y apuntarlos (DNS).
 * Todo se configura en ss-config.php (scloud.cloudflare.*, scloud.domains.enabled); ver ss-config.scloud-sample.php.
 *
 * Registrar API: https://developers.cloudflare.com/registrar/registrar-api/
 */
final class Cloudflare
{
    private const API = 'https://api.cloudflare.com/client/v4';

    /** ¿Está activa la venta de dominios y configurada la API? */
    public static function enabled(): bool
    {
        return (bool) Tenancy::cfg('domains.enabled', true)
            && (string) Tenancy::cfg('cloudflare.api_token', '') !== ''
            && (string) Tenancy::cfg('cloudflare.account_id', '') !== '';
    }

    /** Porcentaje que se suma al costo de Cloudflare. */
    public static function markupPercent(): float
    {
        return max(0.0, (float) Tenancy::cfg('cloudflare.markup_percent', 20));
    }

    /** Precio de venta en la moneda del sitio: costo USD × tasa × (1 + margen), redondeado hacia arriba al peso. */
    public static function sellPrice(float $costUsd): float
    {
        $rate = (float) Tenancy::cfg('cloudflare.usd_rate', 1);
        $raw  = $costUsd * ($rate > 0 ? $rate : 1) * (1 + self::markupPercent() / 100);

        return (float) ceil(round($raw, 4));
    }

    /** @return array decodificado de `result` */
    public static function request(string $method, string $path, ?array $body = null, bool $account = true): array
    {
        $token = (string) Tenancy::cfg('cloudflare.api_token', '');
        if ($token === '') {
            throw new CloudflareException('Falta scloud.cloudflare.api_token en ss-config.php.');
        }
        $url = self::API . ($account ? '/accounts/' . rawurlencode((string) Tenancy::cfg('cloudflare.account_id', '')) : '') . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_USERAGENT      => 'SombrillaCloud/' . SCLOUD_VERSION,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw   = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new CloudflareException('No se pudo conectar con Cloudflare: ' . $error);
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            throw new CloudflareException('Respuesta inválida de Cloudflare (HTTP ' . $code . ').');
        }
        if (empty($json['success']) && $code >= 400) {
            $msg = $json['errors'][0]['message'] ?? ('HTTP ' . $code);
            throw new CloudflareException('Cloudflare: ' . $msg);
        }

        return ['code' => $code] + (is_array($json['result'] ?? null) ? ['result' => $json['result']] : ['result' => []]);
    }

    /** @return array<int, array{name: string, registrable: bool, reason: ?string, cost: ?float, renewal: ?float, price: ?float}> */
    private static function normalize(array $domains): array
    {
        $out = [];
        foreach ($domains as $d) {
            $cost = isset($d['pricing']['registration_cost']) ? (float) $d['pricing']['registration_cost'] : null;
            $out[] = [
                'name'        => strtolower((string) ($d['name'] ?? '')),
                'registrable' => !empty($d['registrable']) && $cost !== null && ($d['tier'] ?? 'standard') === 'standard',
                'reason'      => $d['reason'] ?? (($d['tier'] ?? '') === 'premium' ? 'domain_premium' : null),
                'cost'        => $cost,
                'renewal'     => isset($d['pricing']['renewal_cost']) ? (float) $d['pricing']['renewal_cost'] : null,
                'price'       => $cost !== null ? self::sellPrice($cost) : null,
            ];
        }

        return $out;
    }

    /** Sugerencias a partir de una palabra clave (resultados en caché: solo para descubrir). */
    public static function search(string $query, int $limit = 8): array
    {
        $res = self::request('GET', '/registrar/domain-search?' . http_build_query(['q' => $query, 'limit' => $limit]));

        return self::normalize($res['result']['domains'] ?? []);
    }

    /** Disponibilidad autoritativa de hasta 20 dominios. */
    public static function check(array $domains): array
    {
        $res = self::request('POST', '/registrar/domain-check', ['domains' => array_values($domains)]);

        return self::normalize($res['result']['domains'] ?? []);
    }

    public static function checkOne(string $domain): ?array
    {
        return self::check([$domain])[0] ?? null;
    }

    /**
     * Registra el dominio con el método de pago y el contacto predeterminados de la cuenta.
     * @return array{state: string, completed: bool, error: ?string}
     */
    public static function register(string $domain): array
    {
        $res   = self::request('POST', '/registrar/registrations', ['domain_name' => $domain]);
        $state = (string) ($res['result']['state'] ?? ($res['code'] === 201 ? 'succeeded' : 'in_progress'));

        return [
            'state'     => $state,
            'completed' => !empty($res['result']['completed']) || $state === 'succeeded',
            'error'     => $res['result']['error']['message'] ?? null,
        ];
    }

    /** Estado de un registro en curso: in_progress | succeeded | failed | action_required | blocked. */
    public static function registrationStatus(string $domain): array
    {
        $res   = self::request('GET', '/registrar/registrations/' . rawurlencode($domain) . '/registration-status');
        $state = (string) ($res['result']['state'] ?? 'in_progress');

        return [
            'state'      => $state,
            'completed'  => !empty($res['result']['completed']) || $state === 'succeeded',
            'error'      => $res['result']['error']['message'] ?? null,
            'expires_at' => $res['result']['context']['registration']['expires_at'] ?? null,
        ];
    }

    public static function zoneId(string $domain): ?string
    {
        $res = self::request('GET', '/zones?' . http_build_query(['name' => $domain, 'account.id' => Tenancy::cfg('cloudflare.account_id')]), null, false);

        return $res['result'][0]['id'] ?? null;
    }

    /**
     * Apunta el dominio (y www) a la instancia con CNAME (Cloudflare aplana el CNAME en el apex).
     * Idempotente: actualiza los registros si ya existen. Lanza CloudflareException si la zona aún no existe.
     */
    public static function pointTo(string $domain, string $target): void
    {
        $zone = self::zoneId($domain);
        if (!$zone) {
            throw new CloudflareException("La zona DNS de {$domain} todavía no está disponible en Cloudflare.");
        }
        $proxied = (bool) Tenancy::cfg('cloudflare.dns_proxied', false);
        foreach ([$domain, 'www.' . $domain] as $name) {
            $existing = self::request('GET', "/zones/{$zone}/dns_records?" . http_build_query(['name' => $name]), null, false)['result'];
            $record   = ['type' => 'CNAME', 'name' => $name, 'content' => $target, 'ttl' => 1, 'proxied' => $proxied];
            $id       = null;
            foreach ($existing as $r) {
                if (in_array($r['type'] ?? '', ['A', 'AAAA', 'CNAME'], true)) {
                    $id = $r['id'];
                    break;
                }
            }
            $id
                ? self::request('PUT', "/zones/{$zone}/dns_records/{$id}", $record, false)
                : self::request('POST', "/zones/{$zone}/dns_records", $record, false);
        }
    }
}
