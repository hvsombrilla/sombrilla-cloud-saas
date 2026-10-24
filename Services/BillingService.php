<?php

namespace SombrillaCloud\Services;

use ErpContactos\Contact;
use ErpFacturacion\Models\Invoice;
use ErpFacturacion\Services\InvoiceService;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Models\InvoiceLink;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\PaymentProof;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Support\Cycles;
use SombrillaCloud\Support\Mailer;
use SombrillaCloud\Support\Settings;

/**
 * Integración con el ERP (erp-facturacion / erp-contactos):
 *   - cada cliente es un contacto del ERP;
 *   - cada pedido o renovación genera una factura a crédito (cuenta por cobrar) con origen scloud.*;
 *   - al quedar saldada (pago por transferencia aprobado o registrado desde el ERP) se activa o renueva el servicio.
 */
class BillingService
{
    public const SOURCE_ORDER   = 'scloud.order';
    public const SOURCE_RENEWAL = 'scloud.renewal';

    /** Crea o actualiza el contacto del ERP del cliente. @return array{ok: bool, contact_id?: int|string, errors?: string[]} */
    public static function syncContact(Customer $customer): array
    {
        $data = [
            'name'        => $customer->company ?: $customer->name,
            'email'       => $customer->email,
            'phone'       => $customer->phone,
            'tax_id'      => $customer->tax_id,
            'address'     => $customer->address,
            'city'        => $customer->city,
            'country'     => $customer->country,
            'is_customer' => true,
            'is_company'  => (bool) $customer->company,
            'notes'       => 'Cliente de Sombrilla Cloud #' . $customer->id . ($customer->company ? ' · Contacto: ' . $customer->name : ''),
        ];

        if ($customer->contact_id && Contact::find($customer->contact_id)) {
            $result = Contact::update($customer->contact_id, $data);
        } else {
            $existing = $customer->tax_id ? Contact::findByTaxId(preg_replace('/\D/', '', (string) $customer->tax_id) ?: (string) $customer->tax_id) : null;
            $result   = $existing ? Contact::update($existing->id, $data) : Contact::create($data);
        }

        if (!$result['ok']) {
            return ['ok' => false, 'errors' => $result['errors'] ?? ['No se pudo guardar el contacto en el ERP.']];
        }

        if ((string) $customer->contact_id !== (string) $result['contact']->id) {
            $customer->contact_id = $result['contact']->id;
            $customer->save();
        }

        return ['ok' => true, 'contact_id' => $result['contact']->id];
    }

    /**
     * Crea una factura a crédito en el ERP y la intenta emitir.
     * $lines: [['description', 'quantity', 'unit_price', 'product_id'?], ...]
     * $meta: kind (order|renewal), order_id, service_id, period_from, period_to, due_date, notes
     *
     * @return array{ok: bool, invoice?: Invoice, link?: InvoiceLink, error?: string, issue_error?: ?string}
     */
    public static function createInvoice(Customer $customer, array $lines, array $meta): array
    {
        $contact = self::syncContact($customer);
        if (!$contact['ok']) {
            return ['ok' => false, 'error' => implode(' ', $contact['errors'])];
        }

        $rate  = (float) Settings::get('tax_rate', 18);
        $items = array_map(fn(array $l) => [
            'product_id'  => $l['product_id'] ?? null,
            'description' => $l['description'],
            'quantity'    => $l['quantity'] ?? 1,
            'unit_price'  => $l['unit_price'],
            'discount'    => $l['discount'] ?? 0,
            'itbis_rate'  => $rate,
        ], $lines);

        $kind   = $meta['kind'] ?? 'order';
        $result = InvoiceService::save([
            'client_id'       => $contact['contact_id'],
            'date'            => date('Y-m-d'),
            'due_date'        => $meta['due_date'] ?? date('Y-m-d', strtotime('+' . Settings::int('invoice_due_days') . ' days')),
            'payment_terms'   => 'Transferencia bancaria',
            'payment_method'  => 'credit',
            'delivery_status' => 'not_applicable',
            'notes'           => $meta['notes'] ?? '',
            'items'           => $items,
        ]);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => implode(' ', $result['errors'] ?? ['No se pudo crear la factura.'])];
        }

        /** @var Invoice $invoice */
        $invoice = $result['invoice'];
        $invoice->source    = $kind === 'renewal' ? self::SOURCE_RENEWAL : self::SOURCE_ORDER;
        $invoice->source_id = $kind === 'renewal' ? ($meta['service_id'] ?? null) : ($meta['order_id'] ?? null);
        $invoice->save();

        $link = InvoiceLink::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'kind'        => $kind,
            'order_id'    => $meta['order_id'] ?? null,
            'service_id'  => $meta['service_id'] ?? null,
            'period_from' => $meta['period_from'] ?? null,
            'period_to'   => $meta['period_to'] ?? null,
        ]);

        $issueError = self::issue($invoice);

        return ['ok' => true, 'invoice' => $invoice->fresh('items'), 'link' => $link, 'issue_error' => $issueError];
    }

    /** Emite la factura si sigue en borrador. Devuelve el error o null. */
    public static function issue(Invoice $invoice): ?string
    {
        if (!$invoice->isDraft()) {
            return null;
        }
        $res = InvoiceService::issue($invoice);
        if ($res['ok']) {
            return null;
        }

        $error = (string) ($res['error'] ?? 'No se pudo emitir la factura.');
        ServiceLog::write(null, 'invoice_issue', false, "Factura {$invoice->code}: {$error}");
        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_approve_payments', 'Factura en borrador: ' . $invoice->code, [
                    'type' => 'warning', 'body' => $error, 'url' => url('/erp/invoices/' . $invoice->id),
                    'icon' => 'fa fa-file-invoice', 'source' => 'sombrilla-cloud', 'group' => 'scloud.issue.' . $invoice->id,
                ]);
            } catch (\Throwable) {
            }
        }

        return $error;
    }

    /**
     * Aprueba un comprobante de transferencia: emite la factura si hace falta y registra el pago en el ERP.
     * El hook erp_invoice_payment_registered activa o renueva el servicio cuando la factura queda saldada.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function approveProof(PaymentProof $proof, float $amount, ?int $userId = null, string $notes = ''): array
    {
        if ($proof->status !== 'pending') {
            return ['ok' => false, 'error' => 'Este comprobante ya fue revisado.'];
        }
        $invoice = Invoice::find($proof->invoice_id);
        if (!$invoice) {
            return ['ok' => false, 'error' => 'La factura ya no existe en el ERP.'];
        }
        if ($invoice->status === 'cancelled') {
            return ['ok' => false, 'error' => 'La factura está anulada.'];
        }
        if ($error = self::issue($invoice)) {
            return ['ok' => false, 'error' => 'No se pudo emitir la factura: ' . $error];
        }
        $invoice->refresh();

        $amount = round(min($amount, (float) $invoice->balance), 2);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'La factura no tiene balance pendiente.'];
        }

        // Se marca antes de registrar el pago: el hook del pago puede tardar (creación de la instancia).
        $proof->update([
            'status' => 'approved', 'reviewed_by' => $userId, 'reviewed_at' => now(),
            'review_notes' => $notes !== '' ? $notes : null,
        ]);

        @set_time_limit(300);
        $res = InvoiceService::addPayment($invoice, [
            'amount'    => $amount,
            'date'      => $proof->paid_at ? $proof->paid_at->format('Y-m-d') : date('Y-m-d'),
            'method'    => 'transfer',
            'reference' => (string) $proof->reference,
            'notes'     => 'Transferencia validada (comprobante #' . $proof->id . ($proof->bankAccount ? ' · ' . $proof->bankAccount->bank : '') . ')',
        ], $userId);

        if (!$res['ok']) {
            $proof->update(['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);

            return ['ok' => false, 'error' => $res['error']];
        }
        $proof->update(['payment_id' => $res['payment']->id]);

        $invoice->refresh();
        if ((float) $invoice->balance > 0.01) {
            Mailer::paymentPartial($proof->fresh(['customer', 'invoice']));
        }

        return ['ok' => true];
    }

    public static function rejectProof(PaymentProof $proof, string $reason, ?int $userId = null): array
    {
        if ($proof->status !== 'pending') {
            return ['ok' => false, 'error' => 'Este comprobante ya fue revisado.'];
        }
        $proof->update(['status' => 'rejected', 'review_notes' => $reason, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
        Mailer::proofRejected($proof->fresh(['customer', 'invoice']));

        return ['ok' => true];
    }

    /** Hook erp_invoice_payment_registered: si la factura de Sombrilla Cloud quedó saldada, aplica el pago. */
    public static function onPaymentRegistered(array $payload): void
    {
        $invoice = Invoice::find($payload['invoice_id'] ?? 0);
        if (!$invoice || (float) $invoice->balance > 0.01) {
            return;
        }
        $link = InvoiceLink::where('invoice_id', $invoice->id)->whereNull('processed_at')->first();
        if (!$link) {
            return;
        }
        // Bloqueo optimista: solo una petición procesa la factura.
        if (InvoiceLink::where('id', $link->id)->whereNull('processed_at')->update(['processed_at' => now()]) !== 1) {
            return;
        }

        @set_time_limit(300);
        try {
            if ($link->kind === 'renewal') {
                self::applyRenewal($link);
            } else {
                self::applyOrder($link);
            }
        } catch (\Throwable $e) {
            ServiceLog::write($link->service_id, 'apply_payment', false, $e->getMessage(), $link->customer_id);
        }

        Mailer::paymentConfirmed($link->fresh(['customer', 'invoice']));
    }

    /** Pedido pagado: fija las fechas y crea las instancias. */
    private static function applyOrder(InvoiceLink $link): void
    {
        $order = Order::find($link->order_id);
        if (!$order) {
            return;
        }
        $order->update(['status' => 'paid']);

        $allActive = true;
        foreach (Service::where('order_id', $order->id)->whereIn('status', ['pending', 'failed'])->get() as $service) {
            $service->registration_date = $service->registration_date ?: date('Y-m-d');
            $service->next_due_date     = Cycles::addTo(date('Y-m-d'), $service->billing_cycle)->format('Y-m-d');
            $service->save();
            ServiceLog::write($service->id, 'paid', true, 'Pago del pedido ' . $order->code . ' confirmado.', $service->customer_id);

            $res = Provisioner::create($service);
            $allActive = $allActive && $res['ok'];
        }

        if ($allActive) {
            $order->update(['status' => 'active']);
        }
        do_action('scloud_order_paid', ['order_id' => $order->id]);
    }

    /** Renovación pagada: extiende el vencimiento y reactiva si estaba suspendido por falta de pago. */
    private static function applyRenewal(InvoiceLink $link): void
    {
        $service = Service::find($link->service_id);
        if (!$service) {
            return;
        }
        $from = $link->period_from ?: ($service->next_due_date ?: now());
        $next = Cycles::addTo($from, $service->billing_cycle);
        if (!$service->next_due_date || $next->gt($service->next_due_date)) {
            $service->next_due_date = $next->format('Y-m-d');
            $service->save();
        }
        ServiceLog::write($service->id, 'renewed', true, 'Renovado hasta ' . $service->next_due_date->format('d/m/Y') . '.', $service->customer_id);

        if ($service->status === 'suspended' && $service->suspend_reason === CronService::NONPAYMENT) {
            Provisioner::unsuspend($service);
        }
        do_action('scloud_service_renewed', ['service_id' => $service->id]);
    }

    /** Hook erp_invoice_cancelled: anular la factura de un pedido pendiente cancela el pedido. */
    public static function onInvoiceCancelled(array $payload): void
    {
        if (!empty($payload['reason'])) {
            return; // edición, no anulación
        }
        $link = InvoiceLink::where('invoice_id', $payload['invoice_id'] ?? 0)->first();
        if (!$link || $link->processed_at || $link->kind !== 'order') {
            return;
        }
        $order = Order::find($link->order_id);
        if ($order && $order->status === 'pending') {
            $order->update(['status' => 'cancelled']);
            Service::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        }
    }

    /** Factura abierta (sin procesar ni anular) de la renovación de un servicio para un periodo. */
    public static function renewalInvoiceFor(Service $service, string $periodFrom): ?InvoiceLink
    {
        return InvoiceLink::where('service_id', $service->id)->where('kind', 'renewal')
            ->whereDate('period_from', $periodFrom)
            ->get()
            ->first(fn(InvoiceLink $l) => $l->invoice && $l->invoice->status !== 'cancelled');
    }

    /** Facturas visibles para el cliente: las de su contacto en el ERP (sin borradores ajenos ni anuladas). */
    public static function invoicesFor(Customer $customer)
    {
        if (!$customer->contact_id) {
            return collect();
        }
        $ours = InvoiceLink::where('customer_id', $customer->id)->pluck('invoice_id')->all();

        return Invoice::where('client_id', $customer->contact_id)
            ->where('status', '!=', 'cancelled')
            ->where(fn($q) => $q->where('status', '!=', 'draft')->orWhereIn('id', $ours ?: [0]))
            ->orderByDesc('date')->orderByDesc('id')
            ->get();
    }

    /** Totales de una lista de líneas con el impuesto configurado. */
    public static function totals(float $subtotal): array
    {
        $rate = (float) Settings::get('tax_rate', 18);
        $tax  = round($subtotal * $rate / 100, 2);

        return ['subtotal' => round($subtotal, 2), 'tax' => $tax, 'rate' => $rate, 'total' => round($subtotal + $tax, 2)];
    }
}
