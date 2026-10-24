<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Services\BillingService;
use SombrillaCloud\Services\CronService;
use SombrillaCloud\Services\DomainService;
use SombrillaCloud\Services\PlanLimits;
use SombrillaCloud\Services\Provisioner;
use SombrillaCloud\Support\Cycles;
use SombrillaCloud\Support\Mailer;

class ServiceController
{
    public function index(Request $request)
    {
        $status = (string) $request->query('estado');
        $q      = trim((string) $request->query('q'));
        $services = Service::with('plan', 'customer')
            ->when(isset(Service::STATUSES[$status]), fn($x) => $x->where('status', $status))
            ->when($q !== '', fn($x) => $x->where(fn($w) => $w->where('domain', 'like', "%{$q}%")->orWhere('subdomain', 'like', "%{$q}%")->orWhere('workspace_name', 'like', "%{$q}%")
                ->orWhereIn('customer_id', \SombrillaCloud\Models\Customer::where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->pluck('id'))))
            ->orderByDesc('id')->paginate(25)->appends($request->query());

        return scloud_view('admin.services', ['services' => $services, 'status' => $status, 'q' => $q]);
    }

    public function show(int $id)
    {
        $service = Service::with('plan', 'customer', 'order', 'logs')->findOrFail($id);

        return scloud_view('admin.service', [
            'service' => $service,
            'plans'   => Plan::orderBy('name')->get(),
            'invoices' => \SombrillaCloud\Models\InvoiceLink::with('invoice')->where('service_id', $service->id)->orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $service = Service::findOrFail($id);
        $cycle   = (string) $request->input('billing_cycle');
        $due     = (string) $request->input('next_due_date');
        $service->fill([
            'billing_cycle'    => Cycles::valid($cycle) ? $cycle : $service->billing_cycle,
            'amount'           => max(0, (float) $request->input('amount')),
            'next_due_date'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null,
            'notes'            => trim((string) $request->input('notes')) ?: null,
            'cancel_requested' => (bool) $request->input('cancel_requested'),
        ]);
        $service->save();
        ServiceLog::write($service->id, 'updated', true, 'Datos del servicio editados.', $service->customer_id);
        flash('success', 'Servicio actualizado.');

        return redirect(url('/scloud/servicios/' . $id));
    }

    /** Acciones sobre la instancia: create, suspend, unsuspend, terminate, reset (accesos), login (entrar como administrador), domain (verificar dominio). */
    public function action(Request $request, int $id)
    {
        $service = Service::with('plan', 'customer')->findOrFail($id);
        @set_time_limit(300);
        $action = (string) $request->input('action');

        $res = match ($action) {
            'create'    => Provisioner::create($service),
            'suspend'   => Provisioner::suspend($service, trim((string) $request->input('reason')) ?: 'Suspendido por el administrador'),
            'unsuspend' => Provisioner::unsuspend($service),
            'terminate' => Provisioner::terminate($service),
            'reset'     => Provisioner::resetAdminPassword($service),
            'domain'    => (function () use ($service) { DomainService::sync($service); return ['ok' => true, 'error' => null]; })(),
            'login'     => Provisioner::loginUrl($service),
            'limits'    => (function () use ($service) { return PlanLimits::syncService($service) ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'No se pudieron escribir los límites (la instancia no existe o no hay permisos).']; })(),
            default     => ['ok' => false, 'error' => 'Acción desconocida.'],
        };
        if ($res['ok'] && $action === 'login') {
            ServiceLog::write($service->id, 'staff_login', true, 'Acceso de soporte a la instancia como administrador.', $service->customer_id);
            return redirect($res['url']);
        }
        if ($res['ok'] && $action === 'suspend') {
            Mailer::serviceSuspended($service->fresh(['customer']));
        }
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? ($action === 'limits' ? 'Límites del plan aplicados a la instancia.' : ($action === 'reset' ? 'Contraseña restablecida' . (!empty($res['sent']) ? ' y enviada al cliente.' : ', pero el correo no pudo enviarse.') : 'Acción completada.')) : $res['error']);

        return redirect(url('/scloud/servicios/' . $id));
    }

    /** Genera manualmente la factura de renovación del periodo actual. */
    public function invoice(int $id)
    {
        $service = Service::with('plan', 'customer')->findOrFail($id);
        if (!$service->next_due_date) {
            flash('error', 'El servicio no tiene fecha de vencimiento.');

            return redirect(url('/scloud/servicios/' . $id));
        }
        $from = $service->next_due_date->format('Y-m-d');
        if (BillingService::renewalInvoiceFor($service, $from)) {
            flash('error', 'Ya existe una factura de renovación abierta para este periodo.');

            return redirect(url('/scloud/servicios/' . $id));
        }
        $to  = Cycles::addTo($from, $service->billing_cycle);
        $res = BillingService::createInvoice($service->customer, [[
            'product_id'  => $service->plan->product_id ?? null,
            'description' => 'Renovación instancia ' . ($service->plan->name ?? '') . ($service->host() ? " — {$service->host()}" : '')
                . ' · ' . $service->cycleLabel() . ' (' . $service->next_due_date->format('d/m/Y') . ' - ' . $to->format('d/m/Y') . ')',
            'quantity'    => 1,
            'unit_price'  => $service->amount,
        ]], [
            'kind' => 'renewal', 'service_id' => $service->id, 'period_from' => $from, 'period_to' => $to->format('Y-m-d'),
            'due_date' => $from, 'notes' => 'Renovación de servicio · Sombrilla Cloud',
        ]);
        if ($res['ok']) {
            Mailer::invoiceCreated($service->customer, $res['invoice'], $service);
            ServiceLog::write($service->id, 'renewal_invoice', true, 'Factura de renovación ' . $res['invoice']->code . ' generada manualmente.', $service->customer_id);
            flash('success', 'Factura ' . $res['invoice']->code . ' generada y enviada al cliente.');
        } else {
            flash('error', $res['error']);
        }

        return redirect(url('/scloud/servicios/' . $id));
    }
}
