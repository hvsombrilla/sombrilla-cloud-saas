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

// Da de alta el tema en Webs (el filtro funciona aunque este plugin cargue antes que Webs): id => ruta de su layout.twig.
add_filter('webs_themes', function ($themes) {
    if (is_array($themes)) {
        $themes[SCLOUD_THEME_ID] = ['file' => SCLOUD_THEME_DIR . 'layout.twig', 'source' => 'Sombrilla Cloud'];
    }

    return $themes;
});

// La portada es una página de Webs con Twig (pages/home.twig, layout «wide»). Mientras exista esa página, Studio SaaS no
// registra su portada genérica en «/»; si se borra la página, vuelve la genérica.
add_filter('scloud_register_home', function ($register) {
    if (!$register || !class_exists(\Webs\Models\Page::class)) {
        return $register;
    }
    try {
        return !\Webs\Models\Page::where('slug', '')->exists();
    } catch (\Throwable) {
        return $register;
    }
});

// Crea la página de portada una sola vez (si el tema está activo y aún no hay página «/»). Se hace directo con el modelo
// porque el panel rechaza «/» mientras otro plugin tiene esa ruta; desde la siguiente petición Studio SaaS la libera.
add_action('plugins_loaded', function () {
    if (!class_exists(\Webs\Models\Page::class) || !class_exists(\Webs\Support\Settings::class) || !function_exists('webs_table')) {
        return;
    }
    try {
        if (\Webs\Support\Themes::activeId() !== SCLOUD_THEME_ID || \Webs\Support\Settings::get('sombrilla-cloud.home_seeded')) {
            return;
        }
        if (!\Webs\Models\Page::where('slug', '')->exists()) {
            \Webs\Models\Page::create([
                'title' => 'Sistema para tu negocio: facturación, inventario y ventas',
                'slug' => '',
                'status' => 'published',
                'layout' => 'wide',
                'use_twig' => true,
                'content' => (string) file_get_contents(__DIR__ . '/pages/home.twig'),
                'meta_description' => 'Factura, controla tu inventario, cobra y atiende a tus clientes desde un solo sistema. Sin instalar nada y con ayuda en español.',
                'published_at' => now(),
            ]);
        }
        \Webs\Support\Settings::set('sombrilla-cloud.home_seeded', true);
    } catch (\Throwable $e) {
        error_log('[sombrilla-cloud] No se pudo crear la página de portada: ' . $e->getMessage());
    }
}, 9500);

// El layout del tema ya carga saas.css (también en las páginas de Webs): scloud_frame() no lo vuelve a añadir.
add_filter('scloud_theme_loads_css', function ($loads) {
    return $loads || (class_exists(\Webs\Support\Themes::class) && \Webs\Support\Themes::forModules()?->id === SCLOUD_THEME_ID);
});

/**
 * Datos de Studio SaaS para layout.twig (la plantilla corre en sandbox y no puede llamar a PHP): marca, navegación,
 * carrito, cuenta del cliente y barra inferior. El pie (teléfono, enlaces, horario…) NO está aquí: vive en el theme. Solo se calculan con este tema en uso.
 */
add_filter('webs_twig_context', function ($extra) {
    if (!is_array($extra) || !class_exists(\Webs\Support\TwigEngine::class) || \Webs\Support\TwigEngine::currentTheme()?->id !== SCLOUD_THEME_ID) {
        return $extra;
    }
    $saas  = function_exists('scloud_setting');
    $set   = fn (string $k) => $saas ? (string) scloud_setting($k) : '';
    $brand = $saas ? $set('brand_name') : (string) config('app.name', 'Sombrilla Cloud');
    $path  = '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $on    = fn (string ...$p) => (bool) array_filter($p, fn ($x) => $x === '/' ? $path === '/' : str_starts_with($path, $x));
    $me    = $saas && function_exists('scloud_customer') ? scloud_customer() : null;
    $cart  = $saas && class_exists(\StudioSaas\Support\Cart::class) ? \StudioSaas\Support\Cart::count() : 0;
    // En el flujo de compra la barra inferior deja sitio a la barra con el total y el botón de seguir.
    $flow  = $on('/contratar', '/checkout') || ($on('/carrito') && $cart > 0);
    $tab   = fn ($href, $label, $icon, $active) => ['url' => url($href), 'label' => $label, 'icon' => $icon, 'active' => $active];

    $tabs = $saas ? [
        $tab('/', 'Inicio', 'house', $on('/')),
        $tab('/planes', 'Planes', 'tag', $on('/planes', '/contratar')),
        $tab('/carrito', 'Carrito', 'shopping-cart-simple', $on('/carrito', '/checkout')),
        $tab('/contacto', 'Ayuda', 'headset', $on('/contacto')),
        $tab($me ? '/cliente' : '/cliente/login', $me ? 'Mi cuenta' : 'Ingresar', 'user-circle', $on('/cliente')),
    ] : [];

    $extra['saas'] = [
        'enabled' => $saas, 'brand' => $brand, 'whatsapp' => preg_replace('/\D/', '', $set('whatsapp')),
        'css_url' => function_exists('scloud_asset') ? scloud_asset('saas.css') : '',
        'currency' => function_exists('scloud_currency_symbol') ? scloud_currency_symbol() : '',
        'sample_host' => class_exists(\StudioSaas\Support\Tenancy::class) ? \StudioSaas\Support\Tenancy::host('tunegocio') : 'tunegocio.sombrilla.cloud',
        'home_url' => url('/'), 'cart_url' => url('/carrito'), 'account_url' => url('/cliente'),
        'login_url' => url('/cliente/login'), 'register_url' => url('/cliente/registro'),
        'me' => $me ? ['initials' => $me->initials()] : null, 'cart' => $cart, 'flow' => $flow, 'tabs' => $tabs,
        'nav' => [
            ['label' => 'Inicio', 'url' => url('/'), 'active' => $on('/')],
            ['label' => 'Planes', 'url' => url('/planes'), 'active' => $on('/planes', '/contratar')],
            ['label' => 'Contacto', 'url' => url('/contacto'), 'active' => $on('/contacto')],
        ],
    ];

    return $extra;
});
