<?php

namespace SombrillaCloud\Support;

use Illuminate\Support\Facades\Config;

/**
 * Multi-instancia por subdominio. Se carga desde ss-config.php (antes de arrancar la base de datos y los plugins),
 * así que no depende de nada del plugin salvo la facade Config.
 *
 * Cada instancia tiene, calculados SIN consultar ninguna base de datos:
 *   - su storage:   {data}/{slug}-{hash}/
 *   - su SQLite:    {data}/{slug}-{hash}/database.sqlite   (dentro de su storage)
 * donde {hash} = primeros 16 hex de HMAC-SHA256(slug, scloud.tenant_secret) y {data} = ./data del sitio
 * (o scloud.data_path). ./storage queda solo para el panel principal.
 *
 * Los dominios propios (comprados o apuntados por CNAME) se resuelven con un archivo por dominio en
 * {data}/domains/{dominio} que contiene el slug (tampoco es una base de datos).
 *
 * Configuración (ss-config.php, claves scloud.*): ver ss-config.scloud-sample.php.
 */
final class Tenancy
{
    public const DEFAULT_BANNED = [
        // Infraestructura y correo
        'www', 'mail', 'webmail', 'smtp', 'imap', 'pop', 'pop3', 'mx', 'relay', 'mailer', 'bounce', 'postmaster', 'hostmaster', 'webmaster',
        'autodiscover', 'autoconfig', 'cpanel', 'whm', 'webdisk', 'cpcalendars', 'cpcontacts', 'ftp', 'sftp', 'ssh', 'vpn',
        'ns', 'ns1', 'ns2', 'ns3', 'ns4', 'ns01', 'ns02', 'ns03', 'ns04', 'dns', 'dns1', 'dns2', 'dmarc', '_dmarc', 'spf', 'dkim',
        'localhost', 'server', 'host', 'node', 'cdn', 'static', 'assets', 'files', 'media', 'img', 'images', 'download', 'downloads',
        'db', 'mysql', 'sql', 'phpmyadmin', 'pma', 'adminer', 'redis', 'git', 'svn', 'ci', 'jenkins', 'grafana', 'monitor', 'status', 'uptime',
        // Cuentas, paneles y seguridad
        'admin', 'administrator', 'root', 'sysadmin', 'api', 'app', 'apps', 'panel', 'dashboard', 'console', 'manage', 'portal',
        'login', 'signin', 'signup', 'register', 'auth', 'sso', 'oauth', 'account', 'accounts', 'cuenta', 'secure', 'ssl', 'security',
        'cliente', 'clientes', 'client', 'customer', 'billing', 'invoice', 'invoices', 'factura', 'facturas', 'pagos', 'payments', 'pay',
        // Soporte, comercial y contenido
        'soporte', 'support', 'help', 'ayuda', 'abuse', 'noc', 'info', 'contact', 'contacto', 'ventas', 'sales', 'hello', 'hola',
        'noreply', 'no-reply', 'news', 'newsletter', 'blog', 'docs', 'wiki', 'forum', 'shop', 'store', 'tienda', 'checkout', 'cart', 'carrito',
        'planes', 'plans', 'pricing', 'precios', 'terminos', 'terms', 'privacidad', 'privacy', 'legal',
        // Entornos y marca
        'test', 'testing', 'demo', 'dev', 'develop', 'staging', 'stage', 'prod', 'production', 'beta', 'alpha', 'sandbox', 'preview',
        'wp', 'wordpress', 'woocommerce', 'sombrilla', 'sombrillacloud', 'sombrillastudio', 'studio', 'cloud', 'hvsombrilla',
        'google', 'facebook', 'instagram', 'whatsapp', 'paypal', 'stripe', 'microsoft', 'apple', 'amazon', 'cloudflare',
    ];

    /** Patrones (regex sin delimitadores) que también se rechazan: ns12, mx3, smtp2, www5... */
    public const DEFAULT_BANNED_PATTERNS = [
        '(ns|dns|mx|smtp|imap|pop|pop3|mail|www|ftp|vpn|cdn|node|server|host)[0-9]{1,3}',
        'xn--.*',
    ];

    private static ?string $baseRoot = null;

    public static function cfg(string $key, mixed $default = null): mixed
    {
        $value = Config::get('scloud.' . $key);

        return $value === null ? $default : $value;
    }

    // ── Configuración ─────────────────────────────────────────────────────

    public static function baseDomain(): string
    {
        return strtolower(trim((string) self::cfg('base_domain', 'sombrilla.cloud'), '. '));
    }

    /** ¿Está definido scloud.tenant_secret (32+ caracteres)? Sin él no se pueden calcular las rutas de las instancias. */
    public static function configured(): bool
    {
        return strlen((string) self::cfg('tenant_secret', '')) >= 24;
    }

    public static function secret(): string
    {
        $secret = (string) self::cfg('tenant_secret', '');
        if (strlen($secret) < 24) {
            throw new \RuntimeException("Define Config::set('scloud.tenant_secret', '<cadena aleatoria de 32+ caracteres>') en ss-config.php.");
        }

        return $secret;
    }

    public static function banned(): array
    {
        $list = self::cfg('banned_subdomains');
        $list = is_array($list) ? $list : self::DEFAULT_BANNED;

        return array_values(array_unique(array_map(fn($s) => strtolower(trim((string) $s)), $list)));
    }

    public static function bannedPatterns(): array
    {
        $list = self::cfg('banned_patterns');

        return is_array($list) ? $list : self::DEFAULT_BANNED_PATTERNS;
    }

    // ── Subdominios ───────────────────────────────────────────────────────

    /** Motivo por el que un subdominio no es utilizable (formato o baneado), o null. No mira si ya está ocupado. */
    public static function slugError(string $slug): ?string
    {
        $slug = strtolower(trim($slug));
        $min  = max(2, (int) self::cfg('subdomain_min', 3));
        $max  = min(40, (int) self::cfg('subdomain_max', 30));

        if (strlen($slug) < $min || strlen($slug) > $max) {
            return "El subdominio debe tener entre {$min} y {$max} caracteres.";
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug) || str_contains($slug, '--')) {
            return 'Usa solo letras minúsculas, números y guiones (sin empezar ni terminar con guion).';
        }
        if (in_array($slug, self::banned(), true)) {
            return 'Ese subdominio está reservado. Elige otro.';
        }
        foreach (self::bannedPatterns() as $pattern) {
            if (@preg_match('/^(?:' . $pattern . ')$/', $slug)) {
                return 'Ese subdominio está reservado. Elige otro.';
            }
        }

        return null;
    }

    /** Convierte un texto libre ("Mi Empresa, S.A.") en un candidato válido de subdominio. */
    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return substr($text, 0, (int) self::cfg('subdomain_max', 30)) ?: 'mi-instancia';
    }

    // ── Rutas calculadas (sin base de datos) ──────────────────────────────

    public static function hash(string $slug): string
    {
        return substr(hash_hmac('sha256', $slug, self::secret()), 0, 16);
    }

    public static function key(string $slug): string
    {
        return $slug . '-' . self::hash($slug);
    }

    /** Raíz de datos de las instancias: ./data (o scloud.data_path). ./storage es solo del panel principal. */
    public static function baseRoot(): string
    {
        if (self::$baseRoot === null) {
            $custom = trim((string) self::cfg('data_path', ''));
            $root   = rtrim(str_replace('\\', '/', $custom !== '' ? $custom : SOMBRILLA_STUDIO_PATH . 'data'), '/');
            if (!is_dir($root) && @mkdir($root, 0775, true)) {
                @file_put_contents($root . '/.htaccess', "Require all denied\n");
            }
            self::$baseRoot = $root;
        }

        return self::$baseRoot;
    }

    /** SQLite de la instancia, dentro de su storage. */
    public static function dbFile(string $slug): string
    {
        return self::dir($slug) . '/database.sqlite';
    }

    public static function dir(string $slug): string
    {
        return self::baseRoot() . '/' . self::key($slug);
    }

    public static function exists(string $slug): bool
    {
        return is_file(self::dbFile($slug));
    }

    public static function host(string $slug): string
    {
        return $slug . '.' . self::baseDomain();
    }

    public static function url(string $slug): string
    {
        return 'https://' . self::host($slug);
    }

    // ── Dominios propios ──────────────────────────────────────────────────

    private static function domainFile(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $domain)) {
            return null;
        }

        return self::baseRoot() . '/domains/' . $domain;
    }

    public static function mapDomain(string $domain, string $slug): bool
    {
        $file = self::domainFile($domain);
        if (!$file || self::slugError($slug) !== null) {
            return false;
        }
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return @file_put_contents($file, $slug, LOCK_EX) !== false;
    }

    public static function unmapDomain(string $domain): void
    {
        if ($file = self::domainFile($domain)) {
            @unlink($file);
        }
    }

    /** Slug al que apunta un dominio propio, o null. */
    public static function slugForDomain(string $domain): ?string
    {
        $file = self::domainFile($domain);
        if (!$file || !is_file($file)) {
            return null;
        }
        $slug = trim((string) @file_get_contents($file));

        return preg_match('/^[a-z0-9-]{2,40}$/', $slug) ? $slug : null;
    }

    // ── Límites del plan (los lee el core desde Config limits.*) ───────────

    /** Firma de los límites de una instancia: impide que la propia instancia edite su limits.json. */
    public static function sign(string $slug, array $limits): string
    {
        ksort($limits);

        return hash_hmac('sha256', $slug . '|' . json_encode($limits), self::secret());
    }

    /** Límites escritos para la instancia, o null si no tiene. @throws \RuntimeException si el archivo fue alterado */
    public static function readLimits(string $slug): ?array
    {
        $file = self::dir($slug) . '/limits.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || !is_array($data['limits'] ?? null) || !hash_equals(self::sign($slug, $data['limits']), (string) ($data['sig'] ?? ''))) {
            throw new \RuntimeException('limits.json inválido');
        }

        return $data['limits'];
    }

    // ── Suspensión ────────────────────────────────────────────────────────

    public static function setSuspended(string $slug, bool $suspended): void
    {
        $flag = self::dir($slug) . '/suspended';
        if ($suspended) {
            @file_put_contents($flag, date('c'));
        } else {
            @unlink($flag);
        }
    }

    // ── Arranque (llamar desde ss-config.php) ─────────────────────────────

    public static function inTenant(): bool
    {
        return defined('SCLOUD_TENANT');
    }

    public static function current(): ?string
    {
        return defined('SCLOUD_TENANT') ? SCLOUD_TENANT : null;
    }

    /**
     * Decide si la petición es del panel principal o de una instancia y, en ese caso, apunta la base de datos
     * y el storage a los de la instancia. Una instancia inexistente o reservada responde 404 y termina.
     */
    public static function boot(): void
    {
        // Mismo secreto para firmar (panel principal) y verificar (instancia) el autologin.
        Config::set('app.sso_secret', hash_hmac('sha256', 'sso', self::secret()));

        $slug = null;
        if (PHP_SAPI === 'cli') {
            $slug = getenv('SS_TENANT') ?: null;   // SS_TENANT=acme php artisan ...
        } else {
            $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            $slug = self::slugFromHost($host);
            if ($slug === false) {
                self::deny(404, 'Sitio no encontrado', 'Este sitio no existe o ya no está disponible.');
            }
        }
        if ($slug === null) {
            return; // panel principal
        }

        if (self::slugError($slug) !== null || !self::exists($slug)) {
            self::deny(404, 'Instancia no encontrada', 'Esta instancia no existe o ya no está disponible.');
        }
        if (PHP_SAPI !== 'cli' && is_file(self::dir($slug) . '/suspended')) {
            self::deny(503, 'Instancia suspendida', 'Esta instancia está suspendida. Contacta con quien administra tu cuenta.');
        }

        // Límites del plan (usuarios, plugins, storage). Un archivo alterado deja la instancia fuera de servicio.
        try {
            $limits = self::readLimits($slug);
        } catch (\RuntimeException) {
            error_log("[sombrilla-cloud] limits.json de {$slug} no es válido (firma incorrecta).");
            self::deny(503, 'Instancia en revisión', 'La configuración del plan de esta instancia no es válida. Contacta con quien administra tu cuenta.');
        }

        define('SCLOUD_TENANT', $slug);
        if ($limits) {
            Config::set('limits', $limits);
        }
        Config::set('app.storage_path', self::dir($slug));
        Config::set('app.storage_path_keep', ['database.connections.sqlite.database']);
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', self::dbFile($slug));
        // Una instancia no instala plugins ni actualiza el core: lo gestiona el proveedor.
        Config::set('app.plugin_uploads', false);
        Config::set('app.plugin_delete', false); // /plugins es compartido: una instancia no puede borrar plugins de todos
        Config::set('app.core_updates', false);
        // Los plugins del panel principal no se ofrecen a las instancias; el resto sí pueden activarlos o desactivarlos.
        Config::set('app.plugins_hidden', array_values(array_unique(array_merge(['sombrilla-cloud'], array_map('strval', (array) self::cfg('tenant_hidden_plugins', []))))));
    }

    /** @return string|null|false slug de la instancia; null = panel principal; false = host desconocido. */
    public static function slugFromHost(string $host): string|null|false
    {
        $base = self::baseDomain();
        if ($host === '' || $host === $base || $host === 'www.' . $base || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        $mainHosts = array_map('strtolower', (array) self::cfg('main_hosts', []));
        if (in_array($host, $mainHosts, true)) {
            return null;
        }
        if (str_ends_with($host, '.' . $base)) {
            $label = substr($host, 0, -strlen('.' . $base));

            return str_contains($label, '.') || self::slugError($label) !== null ? false : $label;
        }

        return self::slugForDomain($host) ?? false;
    }

    private static function deny(int $code, string $title, string $text): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title)
            . '</title><body style="font:16px system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f6f7f9;color:#222">'
            . '<div style="text-align:center;padding:24px"><h1 style="margin:0 0 8px;font-size:22px">' . htmlspecialchars($title) . '</h1><p style="margin:0;color:#666">'
            . htmlspecialchars($text) . '</p></div></body>';
        exit;
    }
}
