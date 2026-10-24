<?php
/**
 * Plugin Name: Sombrilla Cloud
 * Plugin URI: https://sombrilla.cloud
 * Description: La línea gráfica de Sombrilla Cloud: un tema de Webs que viste las pantallas de Sombrilla Studio SaaS (portada, planes, carrito y panel del cliente).
 * Version: 3.0.0
 * Author: HV Sombrilla
 * Author URI: https://hvsombrilla.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sombrilla-cloud
 * Requires Plugins: webs, sombrilla-studio-saas
 */

if (!defined('ABSPATH')) {
    exit('No direct script access allowed.');
}

define('SCLOUD_THEME_ID',  'sombrilla-cloud');
define('SCLOUD_THEME_DIR', __DIR__ . '/theme/');

// Da de alta el tema en Webs (el filtro funciona aunque este plugin cargue antes que Webs).
add_filter('webs_themes', function ($themes) {
    if (is_array($themes)) {
        $themes[SCLOUD_THEME_ID] = [
            'name'        => 'Sombrilla Cloud',
            'description' => 'La línea gráfica de Sombrilla Cloud para las pantallas de Sombrilla Studio SaaS.',
            'preview'     => SCLOUD_THEME_DIR . 'preview.svg',
            'file'        => SCLOUD_THEME_DIR . 'theme.php',
            'version'     => '1.0.0',
            'author'      => 'HV Sombrilla',
            'source'      => 'sombrilla-cloud',
        ];
    }

    return $themes;
});

// Con este tema activo, las vistas «saas/*» del tema sustituyen a las genéricas de Studio SaaS (p. ej. la portada).
add_filter('scloud_view_name', function ($name) {
    if (!class_exists(\Webs\Support\Themes::class) || \Webs\Support\Themes::active()?->id !== SCLOUD_THEME_ID) {
        return $name;
    }
    $mine = 'webs-theme-' . SCLOUD_THEME_ID . '::saas.' . substr((string) $name, strlen('scloud::'));

    return str_starts_with((string) $name, 'scloud::') && view()->exists($mine) ? $mine : $name;
});
