<?php

namespace SombrillaCloud\Services;

use Carbon\Carbon;
use ErpFacturacion\Models\Invoice;
use SombrillaCloud\Models\InvoiceLink;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Models\ServiceLog;
use SombrillaCloud\Support\Cycles;
use SombrillaCloud\Support\Mailer;
use SombrillaCloud\Support\Settings;

/**
 * Tarea diaria: facturas de renovación, suspensión por falta de pago y terminación (si está activada).
 * Se ejecuta desde /scloud/cron?key=… o con el botón «Ejecutar ahora» en Ajustes.
 */
class CronService
{
    public const NONPAYMENT = 'Falta de pago';

    public function run(): array
    {
        @set_time_limit(600);
        $stats = ['invoices' => 0, 'suspended' => 0, 'terminated' => 0, 'errors' => []];

        $this->renewals($stats);
        $this->suspensions($stats);
        $this->terminations($stats);

        try {
            \Studio\Models\SsOption::set('scloud_cron_last_run', date('Y-m-d H:i:s'));
        } catch (\Throwable) {
        }

        return $stats;
    }

    /** Genera la factura de renovación N días antes del vencimiento. */
    private function renewals(array &$stats): void
    {
        $limit = Carbon::today()->addDays(max(0, Settings::int('invoice_days_before')));

        $services = Service::with('plan', 'customer')
            ->whereIn('status', ['active', 'suspended'])
            ->where('cancel_requested', false)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', $limit->format('Y-m-d'))
            ->get();

        foreach ($services as $service) {
            $from = $service->next_due_date->format('Y-m-d');
            if (BillingService::renewalInvoiceFor($service, $from)) {
                continue;
            }
            $to = Cycles::addTo($from, $service->billing_cycle);

            $res = BillingService::createInvoice($service->customer, [[
                'product_id'  => $service->plan->product_id ?? null,
                'description' => 'Renovación hosting ' . ($service->plan->name ?? '') . ($service->domain ? " — {$service->domain}" : '')
                    . ' · ' . $service->cycleLabel() . ' (' . $service->next_due_date->format('d/m/Y') . ' - ' . $to->format('d/m/Y') . ')',
                'quantity'    => 1,
                'unit_price'  => $service->amount,
            ]], [
                'kind' => 'renewal', 'service_id' => $service->id, 'period_from' => $from, 'period_to' => $to->format('Y-m-d'),
                'due_date' => $from, 'notes' => 'Renovación de servicio · Sombrilla Cloud',
            ]);

            if ($res['ok']) {
                $stats['invoices']++;
                ServiceLog::write($service->id, 'renewal_invoice', true, 'Factura de renovación ' . $res['invoice']->code . ' generada.', $service->customer_id);
                Mailer::invoiceCreated($service->customer, $res['invoice'], $service);
            } else {
                $stats['errors'][] = $service->title() . ': ' . $res['error'];
                ServiceLog::write($service->id, 'renewal_invoice', false, $res['error'], $service->customer_id);
            }
        }
    }

    /** Suspende servicios vencidos hace más de N días con la renovación sin pagar. */
    private function suspensions(array &$stats): void
    {
        $days = Settings::int('suspend_after_days');
        if ($days <= 0) {
            return;
        }
        $limit = Carbon::today()->subDays($days)->format('Y-m-d');

        $services = Service::with('server', 'plan', 'customer')->where('status', 'active')
            ->whereNotNull('next_due_date')->whereDate('next_due_date', '<', $limit)->get();

        foreach ($services as $service) {
            if (!$this->hasUnpaidRenewal($service)) {
                continue;
            }
            $res = Provisioner::suspend($service, self::NONPAYMENT);
            if ($res['ok']) {
                $stats['suspended']++;
                Mailer::serviceSuspended($service);
            } else {
                $stats['errors'][] = $service->title() . ': ' . $res['error'];
            }
        }
    }

    /** Termina servicios suspendidos por falta de pago hace más de N días (si está activado). */
    private function terminations(array &$stats): void
    {
        $days = Settings::int('terminate_after_days');
        if ($days <= 0) {
            return;
        }
        $limit = Carbon::today()->subDays($days)->format('Y-m-d');

        $services = Service::with('server')->where('status', 'suspended')->where('suspend_reason', self::NONPAYMENT)
            ->whereNotNull('next_due_date')->whereDate('next_due_date', '<', $limit)->get();

        foreach ($services as $service) {
            $res = Provisioner::terminate($service);
            $res['ok'] ? $stats['terminated']++ : $stats['errors'][] = $service->title() . ': ' . $res['error'];
        }
    }

    private function hasUnpaidRenewal(Service $service): bool
    {
        $ids = InvoiceLink::where('service_id', $service->id)->where('kind', 'renewal')->whereNull('processed_at')->pluck('invoice_id');

        return $ids->isNotEmpty() && Invoice::whereIn('id', $ids)->whereNotIn('status', ['cancelled', 'paid'])->exists();
    }
}
