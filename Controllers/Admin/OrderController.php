<?php

namespace SombrillaCloud\Controllers\Admin;

use ErpFacturacion\Models\Invoice;
use ErpFacturacion\Services\InvoiceService;
use Illuminate\Http\Request;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\PaymentProof;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Services\Provisioner;

class OrderController
{
    public function index(Request $request)
    {
        $status = (string) $request->query('estado');
        $q      = trim((string) $request->query('q'));
        $orders = Order::with('customer')
            ->when(isset(Order::STATUSES[$status]), fn($x) => $x->where('status', $status))
            ->when($q !== '', fn($x) => $x->where(fn($w) => $w->where('code', 'like', "%{$q}%")
                ->orWhereIn('customer_id', \SombrillaCloud\Models\Customer::where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->pluck('id'))))
            ->orderByDesc('id')->paginate(25)->appends($request->query());

        return scloud_view('admin.orders', ['orders' => $orders, 'status' => $status, 'q' => $q]);
    }

    public function show(int $id)
    {
        $order = Order::with('customer', 'services.plan')->findOrFail($id);

        return scloud_view('admin.order', [
            'order'   => $order,
            'invoice' => $order->invoice_id ? Invoice::find($order->invoice_id) : null,
            'proofs'  => $order->invoice_id ? PaymentProof::where('invoice_id', $order->invoice_id)->orderByDesc('id')->get() : collect(),
        ]);
    }

    public function cancel(int $id)
    {
        $order = Order::findOrFail($id);
        if ($order->status !== 'pending') {
            flash('error', 'Solo se pueden cancelar pedidos pendientes de pago.');

            return redirect(url('/scloud/pedidos/' . $id));
        }
        // Anular la factura cancela el pedido por el hook erp_invoice_cancelled; si no hay factura, se cancela aquí.
        $invoice = $order->invoice_id ? Invoice::find($order->invoice_id) : null;
        if ($invoice && $invoice->status !== 'cancelled') {
            $res = InvoiceService::cancel($invoice);
            if (!($res['ok'] ?? false)) {
                flash('error', 'No se pudo anular la factura: ' . ($res['error'] ?? 'error desconocido'));

                return redirect(url('/scloud/pedidos/' . $id));
            }
        }
        $order->update(['status' => 'cancelled']);
        Service::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        flash('success', 'Pedido cancelado.');

        return redirect(url('/scloud/pedidos/' . $id));
    }

    /** Reintenta crear las instancias de un pedido pagado (o con error). */
    public function activate(int $id)
    {
        $order = Order::findOrFail($id);
        @set_time_limit(300);
        $errors = [];
        foreach (Service::where('order_id', $order->id)->whereIn('status', ['pending', 'failed'])->get() as $service) {
            if (!$service->next_due_date) {
                $service->next_due_date = \SombrillaCloud\Support\Cycles::addTo(date('Y-m-d'), $service->billing_cycle)->format('Y-m-d');
                $service->save();
            }
            $res = Provisioner::create($service);
            if (!$res['ok']) {
                $errors[] = $service->title() . ': ' . $res['error'];
            }
        }
        if (!$errors) {
            $order->update(['status' => 'active']);
            ServiceLog::write(null, 'order_activated', true, "Pedido {$order->code} activado manualmente.", $order->customer_id);
            flash('success', 'Instancias creadas.');
        } else {
            flash('error', implode(' · ', $errors));
        }

        return redirect(url('/scloud/pedidos/' . $id));
    }
}
