<?php

use Illuminate\Support\Facades\Route;

$site   = 'SombrillaCloud\Controllers\Site\\';
$client = 'SombrillaCloud\Controllers\Client\\';
$admin  = 'SombrillaCloud\Controllers\Admin\\';
$id     = ['id' => '[0-9]+'];

// ── Sitio público ─────────────────────────────────────────────────────────
Route::group(['middleware' => ['scloud-csrf', 'offline:none']], function () use ($site, $id) {
    Route::get('/', ['uses' => $site . 'SiteController@home']);
    Route::get('/planes', ['uses' => $site . 'SiteController@plans']);
    Route::get('/contacto', ['uses' => $site . 'SiteController@contact']);
    Route::get('/contratar/{slug}', ['uses' => $site . 'CartController@configure'])->where('slug', '[a-z0-9-]+');
    Route::get('/carrito', ['uses' => $site . 'CartController@index']);
    Route::post('/carrito', ['uses' => $site . 'CartController@add']);
    Route::get('/dominio/subdominio', ['uses' => $site . 'DomainController@subdomain']);
    Route::get('/dominio/buscar', ['uses' => $site . 'DomainController@search']);
    Route::post('/carrito/cupon', ['uses' => $site . 'CartController@applyCoupon']);
    Route::post('/carrito/cupon/quitar', ['uses' => $site . 'CartController@removeCoupon']);
    Route::post('/carrito/{key}/quitar', ['uses' => $site . 'CartController@remove'])->where('key', '[a-f0-9]+');
    Route::get('/checkout', ['uses' => $site . 'CheckoutController@show']);
    Route::post('/checkout', ['uses' => $site . 'CheckoutController@place']);
});

// Cron diario (público, con llave).
Route::get('/scloud/cron', ['uses' => $admin . 'SettingsController@cron']);

// ── Panel de clientes ─────────────────────────────────────────────────────
Route::group(['middleware' => ['scloud-csrf', 'offline:none']], function () use ($client) {
    $a = $client . 'AuthController';
    Route::get('/cliente/login', ['uses' => "$a@showLogin"]);
    Route::post('/cliente/login', ['uses' => "$a@login"]);
    Route::get('/cliente/registro', ['uses' => "$a@showRegister"]);
    Route::post('/cliente/registro', ['uses' => "$a@register"]);
    Route::post('/cliente/salir', ['uses' => "$a@logout"]);
    Route::get('/cliente/recuperar', ['uses' => "$a@showForgot"]);
    Route::post('/cliente/recuperar', ['uses' => "$a@forgot"]);
    Route::get('/cliente/restablecer/{token}', ['uses' => "$a@showReset"])->where('token', '[a-f0-9]{64}');
    Route::post('/cliente/restablecer/{token}', ['uses' => "$a@reset"])->where('token', '[a-f0-9]{64}');
});

Route::group(['middleware' => ['scloud-csrf', 'scloud-customer', 'offline:none']], function () use ($client, $id) {
    Route::get('/cliente', ['uses' => $client . 'DashboardController@index']);

    $s = $client . 'ServiceController';
    Route::get('/cliente/servicios', ['uses' => "$s@index"]);
    Route::get('/cliente/servicios/{id}', ['uses' => "$s@show"])->where($id);
    Route::post('/cliente/servicios/{id}/abrir', ['uses' => "$s@open"])->where($id);
    Route::post('/cliente/servicios/{id}/dominio/verificar', ['uses' => "$s@verifyDomain"])->where($id);
    Route::post('/cliente/servicios/{id}/cancelar', ['uses' => "$s@cancel"])->where($id);

    $i = $client . 'InvoiceController';
    Route::get('/cliente/facturas', ['uses' => "$i@index"]);
    Route::get('/cliente/facturas/{id}', ['uses' => "$i@show"])->where($id);
    Route::get('/cliente/facturas/{id}/imprimir', ['uses' => "$i@print"])->where($id);
    Route::post('/cliente/facturas/{id}/comprobante', ['uses' => "$i@uploadProof"])->where($id);
    Route::get('/cliente/comprobantes/{id}', ['uses' => "$i@proofFile"])->where($id);

    $t = $client . 'TicketController';
    Route::get('/cliente/tickets', ['uses' => "$t@index"]);
    Route::get('/cliente/tickets/nuevo', ['uses' => "$t@create"]);
    Route::post('/cliente/tickets', ['uses' => "$t@store"]);
    Route::get('/cliente/tickets/{id}', ['uses' => "$t@show"])->where($id);
    Route::post('/cliente/tickets/{id}/responder', ['uses' => "$t@reply"])->where($id);
    Route::post('/cliente/tickets/{id}/cerrar', ['uses' => "$t@close"])->where($id);
    Route::get('/cliente/tickets/adjunto/{id}', ['uses' => "$t@attachment"])->where($id);

    $p = $client . 'ProfileController';
    Route::get('/cliente/perfil', ['uses' => "$p@edit"]);
    Route::post('/cliente/perfil', ['uses' => "$p@update"]);
    Route::post('/cliente/perfil/password', ['uses' => "$p@password"]);

    Route::get('/cliente/pedidos/{code}', ['uses' => $client . 'DashboardController@order'])->where('code', 'SC-[0-9A-Z-]+');
});

// ── Administración (sesión del panel de Sombrilla Studio) ─────────────────
Route::group(['middleware' => ['auth', 'scloud-csrf']], function () use ($admin, $id) {
    Route::get('/scloud', ['uses' => $admin . 'DashboardController@index'])->middleware('permission:scloud_view');

    $o = $admin . 'OrderController';
    Route::get('/scloud/pedidos', ['uses' => "$o@index"])->middleware('permission:scloud_view');
    Route::get('/scloud/pedidos/{id}', ['uses' => "$o@show"])->where($id)->middleware('permission:scloud_view');
    Route::post('/scloud/pedidos/{id}/cancelar', ['uses' => "$o@cancel"])->where($id)->middleware('permission:scloud_manage_orders');
    Route::post('/scloud/pedidos/{id}/activar', ['uses' => "$o@activate"])->where($id)->middleware('permission:scloud_manage_orders');

    $pay = $admin . 'PaymentController';
    Route::get('/scloud/pagos', ['uses' => "$pay@index"])->middleware('permission:scloud_view');
    Route::get('/scloud/pagos/{id}/archivo', ['uses' => "$pay@file"])->where($id)->middleware('permission:scloud_view');
    Route::post('/scloud/pagos/{id}/aprobar', ['uses' => "$pay@approve"])->where($id)->middleware('permission:scloud_approve_payments');
    Route::post('/scloud/pagos/{id}/rechazar', ['uses' => "$pay@reject"])->where($id)->middleware('permission:scloud_approve_payments');

    $s = $admin . 'ServiceController';
    Route::get('/scloud/servicios', ['uses' => "$s@index"])->middleware('permission:scloud_view');
    Route::get('/scloud/servicios/{id}', ['uses' => "$s@show"])->where($id)->middleware('permission:scloud_view');
    Route::post('/scloud/servicios/{id}', ['uses' => "$s@update"])->where($id)->middleware('permission:scloud_manage_services');
    Route::post('/scloud/servicios/{id}/accion', ['uses' => "$s@action"])->where($id)->middleware('permission:scloud_manage_services');
    Route::post('/scloud/servicios/{id}/factura', ['uses' => "$s@invoice"])->where($id)->middleware('permission:scloud_manage_services');

    $c = $admin . 'CustomerController';
    Route::get('/scloud/clientes', ['uses' => "$c@index"])->middleware('permission:scloud_view');
    Route::get('/scloud/clientes/{id}', ['uses' => "$c@show"])->where($id)->middleware('permission:scloud_view');
    Route::post('/scloud/clientes/{id}', ['uses' => "$c@update"])->where($id)->middleware('permission:scloud_manage_customers');

    $t = $admin . 'TicketController';
    Route::get('/scloud/tickets', ['uses' => "$t@index"])->middleware('permission:scloud_view');
    Route::get('/scloud/tickets/{id}', ['uses' => "$t@show"])->where($id)->middleware('permission:scloud_view');
    Route::post('/scloud/tickets/{id}/responder', ['uses' => "$t@reply"])->where($id)->middleware('permission:scloud_manage_tickets');
    Route::post('/scloud/tickets/{id}/estado', ['uses' => "$t@status"])->where($id)->middleware('permission:scloud_manage_tickets');
    Route::get('/scloud/tickets/adjunto/{id}', ['uses' => "$t@attachment"])->where($id)->middleware('permission:scloud_view');

    $pl = $admin . 'PlanController';
    Route::get('/scloud/planes', ['uses' => "$pl@index"])->middleware('permission:scloud_manage_plans');
    Route::get('/scloud/planes/nuevo', ['uses' => "$pl@create"])->middleware('permission:scloud_manage_plans');
    Route::post('/scloud/planes', ['uses' => "$pl@store"])->middleware('permission:scloud_manage_plans');
    Route::get('/scloud/planes/{id}', ['uses' => "$pl@edit"])->where($id)->middleware('permission:scloud_manage_plans');
    Route::post('/scloud/planes/{id}', ['uses' => "$pl@update"])->where($id)->middleware('permission:scloud_manage_plans');
    Route::post('/scloud/planes/{id}/eliminar', ['uses' => "$pl@destroy"])->where($id)->middleware('permission:scloud_manage_plans');

    $cp = $admin . 'CouponController';
    Route::get('/scloud/cupones', ['uses' => "$cp@index"])->middleware('permission:scloud_manage_plans');
    Route::get('/scloud/cupones/nuevo', ['uses' => "$cp@create"])->middleware('permission:scloud_manage_plans');
    Route::post('/scloud/cupones', ['uses' => "$cp@store"])->middleware('permission:scloud_manage_plans');
    Route::get('/scloud/cupones/{id}', ['uses' => "$cp@edit"])->where($id)->middleware('permission:scloud_manage_plans');
    Route::post('/scloud/cupones/{id}', ['uses' => "$cp@update"])->where($id)->middleware('permission:scloud_manage_plans');
    Route::post('/scloud/cupones/{id}/eliminar', ['uses' => "$cp@destroy"])->where($id)->middleware('permission:scloud_manage_plans');

    $st = $admin . 'SettingsController';
    Route::get('/scloud/ajustes', ['uses' => "$st@index"])->middleware('permission:scloud_manage_settings');
    Route::post('/scloud/ajustes', ['uses' => "$st@update"])->middleware('permission:scloud_manage_settings');
    Route::post('/scloud/ajustes/bancos', ['uses' => "$st@saveBank"])->middleware('permission:scloud_manage_settings');
    Route::post('/scloud/ajustes/bancos/{id}/eliminar', ['uses' => "$st@deleteBank"])->where($id)->middleware('permission:scloud_manage_settings');
    Route::post('/scloud/ajustes/cron', ['uses' => "$st@runCron"])->middleware('permission:scloud_manage_settings');
});
