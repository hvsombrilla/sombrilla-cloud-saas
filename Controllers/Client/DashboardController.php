<?php

namespace SombrillaCloud\Controllers\Client;

use SombrillaCloud\Models\BankAccount;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\Ticket;
use SombrillaCloud\Services\BillingService;

class DashboardController
{
    public function index()
    {
        $customer = scloud_customer();
        $invoices = BillingService::invoicesFor($customer);

        return scloud_view('client.dashboard', [
            'services' => $customer->services()->whereNotIn('status', ['cancelled'])->with('plan')->limit(6)->get(),
            'unpaid'   => $invoices->filter(fn($i) => in_array($i->status, ['draft', 'issued', 'partial', 'overdue'], true))->values(),
            'tickets'  => $customer->tickets()->where('status', '!=', 'closed')->limit(5)->get(),
            'orders'   => $customer->orders()->limit(3)->get(),
        ]);
    }

    public function order(string $code)
    {
        $order = Order::where('code', $code)->where('customer_id', scloud_customer()->id)->with('services.plan')->first();
        if (!$order) {
            scloud_not_found();
        }
        $invoice = $order->invoice_id ? \ErpFacturacion\Models\Invoice::find($order->invoice_id) : null;

        return scloud_view('client.order', [
            'order'   => $order,
            'invoice' => $invoice,
            'banks'   => BankAccount::active()->get(),
        ]);
    }
}
