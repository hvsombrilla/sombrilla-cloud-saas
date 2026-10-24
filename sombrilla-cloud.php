<?php
/**
 * Plugin Name: Sombrilla Cloud
 * Plugin URI: https://sombrilla.cloud
 * Description: Vende instancias de Sombrilla Studio con tienda, pagos y dominios propios.
 * Version: 2.0.0
 * Author: HV Sombrilla
 * Author URI: https://hvsombrilla.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sombrilla-cloud
 * Requires Plugins: sombrilla-erp, erp-contactos, erp-stock, erp-facturacion
 */

if (!defined('ABSPATH')) {
    exit('No direct script access allowed.');
}

define('SCLOUD_DIR',        __DIR__ . '/');
define('SCLOUD_URL',        '/plugins/sombrilla-cloud/');
define('SCLOUD_VERSION',    '2.0.0');
define('SCLOUD_DB_VERSION', '4');

// Autoloader del plugin (namespace raíz: SombrillaCloud\)
spl_autoload_register(function (string $class): void {
    $prefix = 'SombrillaCloud\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = SCLOUD_DIR . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

add_filter('ss_admin_menu', function (array $menu): array {
    $user = function_exists('ss_current_user') ? ss_current_user() : null;
    if (!$user || !$user->can('scloud_view')) {
        return $menu;
    }

    $menu[] = ['id' => 'scloud', 'name' => 'Sombrilla Cloud', 'url' => url('/scloud'), 'icon' => 'ph ph-cloud'];
    $menu[] = ['name' => 'Panel', 'url' => url('/scloud'), 'parent' => 'scloud'];
    $menu[] = ['name' => 'Pedidos', 'url' => url('/scloud/pedidos'), 'parent' => 'scloud'];
    $menu[] = ['name' => 'Pagos por validar', 'url' => url('/scloud/pagos'), 'parent' => 'scloud'];
    $menu[] = ['name' => 'Servicios', 'url' => url('/scloud/servicios'), 'parent' => 'scloud'];
    $menu[] = ['name' => 'Clientes', 'url' => url('/scloud/clientes'), 'parent' => 'scloud'];
    $menu[] = ['name' => 'Tickets', 'url' => url('/scloud/tickets'), 'parent' => 'scloud'];
    if ($user->can('scloud_manage_plans')) {
        $menu[] = ['name' => 'Planes', 'url' => url('/scloud/planes'), 'parent' => 'scloud'];
    }
    if ($user->can('scloud_manage_settings')) {
        $menu[] = ['name' => 'Ajustes', 'url' => url('/scloud/ajustes'), 'parent' => 'scloud'];
    }

    return $menu;
});

add_action('plugins_loaded', function () {
    // Este plugin es del panel principal: dentro de una instancia (cliente) nunca se carga, aunque se active.
    if (class_exists(\SombrillaCloud\Support\Tenancy::class) && \SombrillaCloud\Support\Tenancy::inTenant()) {
        return;
    }
    if (!function_exists('sf_route') || !defined('ERP_FACTURACION_DIR') || !defined('ERP_CONTACTOS_DIR')) {
        return; // Requiere «Facturación (ERP)» y «Contactos»
    }

    include SCLOUD_DIR . 'includes/helpers.php';
    include SCLOUD_DIR . 'includes/loader.php';
    include SCLOUD_DIR . 'includes/routes.php';
    include SCLOUD_DIR . 'includes/integrations.php';
});
