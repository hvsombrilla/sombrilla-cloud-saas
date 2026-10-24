<?php

namespace SombrillaCloud\Controllers\Client;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Services\BillingService;
use SombrillaCloud\Services\Provisioner;
use SombrillaCloud\Services\DomainService;
use SombrillaCloud\Support\Tenancy;

class ServiceController
{
    public function index()
    {
        return scloud_view('client.services', [
            'services' => scloud_customer()->services()->with('plan')->get(),
        ]);
    }

    public function show(int $id)
    {
        $service = $this->own($id);
        $renewal = $service->next_due_date ? BillingService::renewalInvoiceFor($service, $service->next_due_date->format('Y-m-d')) : null;
        $dns     = $service->domain && $service->domain_mode === 'own' && $service->subdomain ? DomainService::dnsInstructions($service->subdomain, $service->domain) : null;

        return scloud_view('client.service', ['service' => $service, 'renewal' => $renewal, 'dns' => $dns]);
    }

    /** Autologin: abre la instancia ya autenticada como su administrador (token firmado de un solo uso). */
    public function open(int $id)
    {
        $service = $this->own($id);
        $res     = Provisioner::loginUrl($service);
        if (!$res['ok']) {
            flash('error', $res['error']);

            return redirect(url('/cliente/servicios/' . $service->id));
        }
        ServiceLog::write($service->id, 'autologin', true, 'Acceso a la instancia desde el panel de clientes.', $service->customer_id);

        return redirect($res['url']);
    }

    /** Comprueba si el CNAME del dominio propio ya apunta a la instancia. */
    public function verifyDomain(int $id)
    {
        $service = $this->own($id);
        if (!$service->domain || $service->domain_status === 'connected') {
            return redirect(url('/cliente/servicios/' . $service->id));
        }
        DomainService::sync($service);
        $service->refresh();
        if ($service->domain_status === 'connected') {
            flash('success', '¡Listo! ' . $service->domain . ' ya apunta a tu instancia. Puede tardar unos minutos en activarse el certificado SSL.');
        } else {
            flash('error', 'Todavía no vemos el CNAME. Los cambios de DNS pueden tardar hasta unas horas en propagarse; vuelve a intentarlo luego.');
        }

        return redirect(url('/cliente/servicios/' . $service->id));
    }

    /** Solicita la cancelación: se aplica al vencimiento y evita nuevas facturas de renovación. */
    public function cancel(Request $request, int $id)
    {
        $service = $this->own($id);
        if (!$service->isLive() || $service->cancel_requested) {
            return redirect(url('/cliente/servicios/' . $service->id));
        }
        $service->update(['cancel_requested' => true]);
        ServiceLog::write($service->id, 'cancel_requested', true, 'El cliente solicitó la cancelación. ' . trim((string) $request->input('reason')), $service->customer_id);
        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_manage_services', 'Cancelación solicitada: ' . $service->title(), [
                    'type' => 'warning', 'body' => scloud_customer()->name, 'url' => url('/scloud/servicios/' . $service->id),
                    'icon' => 'fa fa-ban', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }
        flash('success', 'Recibimos tu solicitud de cancelación. El servicio seguirá activo hasta su vencimiento y no se generarán más renovaciones.');

        return redirect(url('/cliente/servicios/' . $service->id));
    }

    private function own(int $id): Service
    {
        $service = Service::with('plan', 'order')->where('id', $id)->where('customer_id', scloud_customer()->id)->first();
        if (!$service) {
            scloud_not_found();
        }

        return $service;
    }
}
