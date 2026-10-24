<?php

use Illuminate\Support\Facades\View;
use SombrillaCloud\Services\BillingService;

View::addNamespace('scloud', SCLOUD_DIR . 'Views');

\SombrillaCloud\Migrations::run();

app('router')->aliasMiddleware('scloud-customer', \SombrillaCloud\Middleware\CustomerAuth::class);
app('router')->aliasMiddleware('scloud-csrf', \SombrillaCloud\Middleware\VerifyCsrf::class);

register_permission_group('scloud', 'Sombrilla Cloud', [
    'scloud_view'             => 'Ver pedidos, servicios, clientes y tickets',
    'scloud_manage_orders'    => 'Gestionar pedidos',
    'scloud_approve_payments' => 'Validar comprobantes de transferencia',
    'scloud_manage_services'  => 'Gestionar servicios (crear, suspender, archivar instancias)',
    'scloud_manage_customers' => 'Editar clientes',
    'scloud_manage_tickets'   => 'Responder y gestionar tickets',
    'scloud_manage_plans'     => 'Gestionar planes',
    'scloud_manage_settings'  => 'Ajustes de Sombrilla Cloud',
], 'ph ph-cloud');

// Las facturas de Sombrilla Cloud son a crédito (cuenta por cobrar) pero sin límite de crédito ni mora:
// el ciclo de cobro (renovación, suspensión) lo lleva este plugin.
add_filter('erp_invoice_sources', function ($sources) {
    $sources[BillingService::SOURCE_ORDER] = [
        'label' => 'Pedido Sombrilla Cloud', 'skip_credit_check' => true, 'skip_late_fee' => true, 'skip_reminders' => false,
        'badge_url' => fn(int $id) => url('/scloud/pedidos/' . $id), 'badge_label' => 'Ver pedido',
    ];
    $sources[BillingService::SOURCE_RENEWAL] = [
        'label' => 'Renovación Sombrilla Cloud', 'skip_credit_check' => true, 'skip_late_fee' => true, 'skip_reminders' => false,
        'badge_url' => fn(int $id) => url('/scloud/servicios/' . $id), 'badge_label' => 'Ver servicio',
    ];

    return $sources;
});

// Pagos registrados en el ERP (desde aquí o desde Facturación › Pagos) activan/renuevan servicios.
add_action('erp_invoice_payment_registered', function ($payload) {
    if (is_array($payload)) {
        BillingService::onPaymentRegistered($payload);
    }
}, 20);

add_action('erp_invoice_cancelled', function ($payload) {
    if (is_array($payload)) {
        BillingService::onInvoiceCancelled($payload);
    }
});

// Un cliente con servicios no se elimina desde Contactos.
add_filter('erp_contact_delete_blockers', function ($payload) {
    if (is_array($payload) && \SombrillaCloud\Models\Customer::where('contact_id', $payload['id'])->exists()) {
        $payload['reasons'][] = 'El contacto es un cliente de Sombrilla Cloud.';
    }

    return $payload;
});
