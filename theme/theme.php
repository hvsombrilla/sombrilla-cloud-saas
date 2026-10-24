<?php
/**
 * Theme Name: Sombrilla Cloud
 * Description: La línea gráfica de Sombrilla Cloud: cabecera con carrito y cuenta, portada con planes y pie con datos de contacto. Se apoya en Studio SaaS.
 * Preview: preview.svg
 * Version: 1.0.0
 * Author: HV Sombrilla
 *
 * @var \Webs\Support\Theme $theme
 */

$theme->views('views');

$theme->layout('main', [
    'name' => 'Principal',
    'description' => 'Cabecera, contenido centrado y pie de página.',
    'view' => 'main',
    'contexts' => ['page'],
]);
$theme->layout('wide', [
    'name' => 'Ancho completo',
    'description' => 'Cabecera y pie con el contenido a todo el ancho. También se usa en el blog, la tienda y las pantallas de Studio SaaS (planes, carrito, panel del cliente).',
    'view' => 'wide',
    'contexts' => ['page', 'blog', 'shop', 'saas'],
]);

$theme->style('assets/theme.css');

$theme->defaults([
    'color_primary' => '#2b5cff',
    'color_accent' => '#7a4dff',
    'color_bg' => '#f6f8fc',
    'color_surface' => '#ffffff',
    'color_text' => '#172033',
    'color_muted' => '#5b6578',
    'color_border' => '#e3e8f1',
    'color_header_bg' => '#ffffff',
    'color_header_text' => '#172033',
    'color_footer_bg' => '#0c1633',
    'color_footer_text' => '#cfd9ff',
    'font_heading' => 'inter',
    'font_body' => 'inter',
    'radius' => 14,
]);
