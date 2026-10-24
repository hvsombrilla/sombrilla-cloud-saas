<?php
/**
 * Sombrilla Cloud — configuración de instancias (se copia a ss-config.php del sitio principal).
 *
 * Todas las instancias (clientes) corren con ESTE mismo código y ESTE mismo ss-config.php: el comodín
 * *.sombrilla.cloud apunta a este directorio y, según el host de la petición, se usa la base de datos
 * y el storage de la instancia correspondiente. Sin consultar ninguna otra base de datos.
 *
 *   Storage de la instancia: {data}/{subdominio}-{hash}/
 *   SQLite de la instancia:  {data}/{subdominio}-{hash}/database.sqlite
 *   {data} = ./data del sitio (cámbialo con scloud.data_path). ./storage queda solo para el panel principal.
 *   hash = primeros 16 hex de HMAC-SHA256(subdominio, scloud.tenant_secret)
 */

defined('SOMBRILLA_STUDIO_PATH') or exit;

use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| 1. Instancias (obligatorio)
|--------------------------------------------------------------------------
*/

// Dominio base: las instancias viven en {subdominio}.{base_domain}.
Config::set('scloud.base_domain', 'sombrilla.cloud');

// Secreto (32+ caracteres aleatorios). Define el {hash} de cada instancia y firma el autologin.
// NO lo cambies una vez que haya instancias: dejarían de encontrarse sus archivos. Genera uno con:
//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
Config::set('scloud.tenant_secret', 'CAMBIA-ESTO-por-una-cadena-aleatoria-de-64-hex');

// Carpeta donde viven las instancias (storage + SQLite de cada una). Por defecto ./data del sitio.
// Config::set('scloud.data_path', '/var/lib/sombrilla/data');

// Hosts extra que sirven el panel principal (además de la raíz y www).
// Config::set('scloud.main_hosts', ['panel.sombrilla.cloud']);

// IP pública del servidor (opcional): se muestra como alternativa A al CNAME para el dominio raíz.
// Config::set('scloud.server_ip', '203.0.113.10');

// Límites del subdominio elegido por el cliente.
// Config::set('scloud.subdomain_min', 3);
// Config::set('scloud.subdomain_max', 30);

// Plugins del sistema que NO se muestran a las instancias (sombrilla-cloud ya está siempre oculto).
// Config::set('scloud.tenant_hidden_plugins', ['god-mode']);

// Los límites de usuarios, plugins activos, almacenamiento y plugins permitidos NO se configuran aquí: se definen en cada plan
// (Sombrilla Cloud › Planes). Una instalación del core sin este plugin puede usar Config::set('limits', [...]) (ver docs/PLUGIN-DEVELOPMENT.md §18).

// Plugins que vienen ya activados en cada instancia nueva (el admin de la instancia puede desactivarlos). Por defecto, ninguno:
// sin esto, cada instancia activa los que quiera, tanto los de /plugins como los de su storage/plugins.
// Config::set('scloud.tenant_default_plugins', ['erp-contactos']);

/*
|--------------------------------------------------------------------------
| 2. Subdominios reservados (nadie puede elegirlos)
|--------------------------------------------------------------------------
| Si defines esta lista REEMPLAZA a la de fábrica (SombrillaCloud\Support\Tenancy::DEFAULT_BANNED),
| así que parte de ella. Para sumar a la de fábrica sin copiarla:
|   Config::set('scloud.banned_subdomains', array_merge(\SombrillaCloud\Support\Tenancy::DEFAULT_BANNED, ['miempresa']));
*/
// Config::set('scloud.banned_subdomains', [
//     'www', 'mail', 'webmail', 'smtp', 'imap', 'pop', 'pop3', 'mx', 'ftp', 'sftp', 'ssh', 'vpn',
//     'cpanel', 'whm', 'webdisk', 'autodiscover', 'autoconfig', 'cpcalendars', 'cpcontacts',
//     'ns', 'ns1', 'ns2', 'ns3', 'ns4', 'ns01', 'ns02', 'dns', 'dmarc', '_dmarc', 'spf', 'dkim',
//     'admin', 'administrator', 'root', 'api', 'app', 'panel', 'dashboard', 'console', 'portal',
//     'login', 'auth', 'sso', 'account', 'cuenta', 'cliente', 'clientes', 'billing', 'facturas',
//     'soporte', 'support', 'help', 'ayuda', 'info', 'ventas', 'contacto', 'status', 'blog',
//     'test', 'demo', 'dev', 'staging', 'beta', 'sandbox', 'sombrilla', 'studio', 'cloud',
// ]);

// Patrones (regex sin delimitadores) también reservados: ns12, mx3, smtp2, www5...
// Config::set('scloud.banned_patterns', ['(ns|dns|mx|smtp|imap|pop|pop3|mail|www|ftp)[0-9]{1,3}', 'xn--.*']);

/*
|--------------------------------------------------------------------------
| 3. Dominios
|--------------------------------------------------------------------------
*/

// «Ya tengo dominio, lo apuntaré»: el cliente crea un CNAME hacia su {subdominio}.{base_domain}.
Config::set('scloud.domains.own_enabled', true);

// «Comprar dominio» (Cloudflare Registrar). false = solo subdominio y dominio propio.
Config::set('scloud.domains.enabled', false);

// Cloudflare: token de API con permisos de Registrar (escritura), Zone Read y DNS Edit.
// La cuenta necesita método de pago, contacto de registrante y aceptar el acuerdo de registro en el panel de Cloudflare.
Config::set('scloud.cloudflare.account_id', '');
Config::set('scloud.cloudflare.api_token', '');

// Margen sobre el precio de Cloudflare, en %. Precio = costo USD × tasa × (1 + margen/100), redondeado hacia arriba.
Config::set('scloud.cloudflare.markup_percent', 20);

// Tasa USD → moneda del sitio (Cloudflare cobra en USD). 1 si el sitio factura en USD.
Config::set('scloud.cloudflare.usd_rate', 60);

// Proxy de Cloudflare (nube naranja) en los registros DNS de los dominios comprados.
// Config::set('scloud.cloudflare.dns_proxied', false);

/*
|--------------------------------------------------------------------------
| 4. Arranque multi-instancia (déjalo AL FINAL de este archivo)
|--------------------------------------------------------------------------
| Decide por el host si la petición es del panel principal o de una instancia y apunta la base de datos y el
| storage. Una instancia inexistente responde 404 y termina aquí. En CLI: SS_TENANT=acme php artisan ...
*/
if (is_file(__DIR__ . '/plugins/sombrilla-cloud/Support/Tenancy.php')) {
    require_once __DIR__ . '/plugins/sombrilla-cloud/Support/Tenancy.php';
    \SombrillaCloud\Support\Tenancy::boot();
}
