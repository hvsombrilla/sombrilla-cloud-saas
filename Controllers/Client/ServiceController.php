<?php

namespace SombrillaCloud\Controllers\Client;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Services\BillingService;
use SombrillaCloud\Services\Provisioner;
use SombrillaCloud\Services\Whm;
use SombrillaCloud\Services\WhmException;

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
        $usage   = null;
        if ($service->status === 'active' && $service->username && $service->server) {
            try {
                $usage = Whm::for($service->server)->summary($service->username);
            } catch (WhmException) {
                $usage = null;
            }
        }
        $renewal = $service->next_due_date ? BillingService::renewalInvoiceFor($service, $service->next_due_date->format('Y-m-d')) : null;

        return scloud_view('client.service', ['service' => $service, 'usage' => $usage, 'renewal' => $renewal]);
    }

    public function cpanel(int $id)
    {
        $service = $this->own($id);
        $res     = Provisioner::cpanelUrl($service);
        if (!$res['ok']) {
            flash('error', $res['error']);

            return redirect(url('/cliente/servicios/' . $service->id));
        }
        ServiceLog::write($service->id, 'cpanel_login', true, 'Acceso a cPanel desde el panel de clientes.', $service->customer_id);

        return redirect($res['url']);
    }

    public function password(Request $request, int $id)
    {
        $service  = $this->own($id);
        $password = (string) $request->input('password');
        $back     = '/cliente/servicios/' . $service->id;

        if ($service->status !== 'active') {
            return scloud_back_with($back, ['Solo puedes cambiar la contraseña de un servicio activo.']);
        }
        if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            return scloud_back_with($back, ['La contraseña debe tener al menos 12 caracteres, con mayúsculas, minúsculas y números.']);
        }
        if ($password !== (string) $request->input('password_confirmation')) {
            return scloud_back_with($back, ['Las contraseñas no coinciden.']);
        }
        $res = Provisioner::changePassword($service, $password);
        if (!$res['ok']) {
            return scloud_back_with($back, ['No se pudo cambiar la contraseña: ' . $res['error']]);
        }
        flash('success', 'Contraseña de cPanel actualizada.');

        return redirect(url($back));
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
        $service = Service::with('plan', 'server', 'order')->where('id', $id)->where('customer_id', scloud_customer()->id)->first();
        if (!$service) {
            scloud_not_found();
        }

        return $service;
    }
}
