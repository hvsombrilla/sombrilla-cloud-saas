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

// Chatter y auditoría (Ajustes › Avanzado › Auditoría). Ver docs/CHATTER.md del core.
foreach ([
    ['scloud_orders',    'Pedidos',         'ph ph-shopping-cart',   'scloud_view', 'scloud_manage_orders',    \SombrillaCloud\Models\Order::class,    '/scloud/pedidos/{id}'],
    ['scloud_services',  'Servicios',       'ph ph-cloud',           'scloud_view', 'scloud_manage_services',  \SombrillaCloud\Models\Service::class,  '/scloud/servicios/{id}'],
    ['scloud_customers', 'Clientes',        'ph ph-users-three',     'scloud_view', 'scloud_manage_customers', \SombrillaCloud\Models\Customer::class, '/scloud/clientes/{id}'],
    ['scloud_tickets',   'Tickets',         'ph ph-lifebuoy',        'scloud_view', 'scloud_manage_tickets',   \SombrillaCloud\Models\Ticket::class,   '/scloud/tickets/{id}'],
    ['scloud_plans',     'Planes',          'ph ph-stack',           'scloud_manage_plans', null,              \SombrillaCloud\Models\Plan::class,     '/scloud/planes/{id}'],
    ['scloud_coupons',   'Cupones',         'ph ph-ticket',          'scloud_manage_plans', null,              \SombrillaCloud\Models\Coupon::class,   '/scloud/cupones/{id}'],
    ['scloud_payment_proofs', 'Comprobantes de pago', 'ph ph-receipt', 'scloud_view', 'scloud_approve_payments', \SombrillaCloud\Models\PaymentProof::class, null],
    ['scloud_bank_accounts',  'Cuentas bancarias',    'ph ph-bank',    'scloud_manage_settings', null,         \SombrillaCloud\Models\BankAccount::class, null],
] as [$__slug, $__label, $__icon, $__perm, $__write, $__model, $__url]) {
    ss_chatter_entity($__slug, [
        'label' => $__label, 'group' => 'Sombrilla Cloud', 'icon' => $__icon, 'permission' => $__perm,
        'write_permission' => $__write, 'model' => $__model, 'url' => $__url,
    ]);
}

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
