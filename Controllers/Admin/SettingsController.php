<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\BankAccount;
use SombrillaCloud\Services\Cloudflare;
use SombrillaCloud\Services\CronService;
use SombrillaCloud\Support\Tenancy;
use SombrillaCloud\Support\Settings;
use Studio\Models\SsOption;

class SettingsController
{
    public function index()
    {
        return scloud_view('admin.settings', [
            's'       => Settings::all(),
            'banks'   => BankAccount::orderBy('sort_order')->orderBy('id')->get(),
            'cronUrl' => url('/scloud/cron') . '?key=' . Settings::cronKey(),
            'cronLast' => SsOption::get('scloud_cron_last_run'),
            'infra'   => [
                'base_domain'     => Tenancy::baseDomain(),
                'secret'          => strlen((string) Tenancy::cfg('tenant_secret', '')) >= 24,
                'banned'          => count(Tenancy::banned()),
                'own'             => (bool) Tenancy::cfg('domains.own_enabled', true),
                'domains_enabled' => (bool) Tenancy::cfg('domains.enabled', true),
                'cloudflare'      => Cloudflare::enabled(),
                'markup'          => Cloudflare::markupPercent(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $values = [];
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            if ($key === 'cron_key' || !$request->has($key)) {
                continue;
            }
            $values[$key] = in_array($key, ['tax_rate', 'invoice_due_days', 'invoice_days_before', 'suspend_after_days', 'terminate_after_days'], true)
                ? max(0, (float) $request->input($key)) : (string) $request->input($key);
        }
        foreach (['support_email', 'mail_from_email', 'admin_notify_email'] as $k) {
            if (($values[$k] ?? '') !== '' && !filter_var($values[$k], FILTER_VALIDATE_EMAIL)) {
                flash('error', 'El correo «' . $values[$k] . '» no es válido.');

                return redirect(url('/scloud/ajustes'));
            }
        }
        Settings::save($values);
        flash('success', 'Ajustes guardados.');

        return redirect(url('/scloud/ajustes'));
    }

    public function saveBank(Request $request)
    {
        $bank = trim((string) $request->input('bank'));
        $num  = trim((string) $request->input('account_number'));
        if ($bank === '' || $num === '' || trim((string) $request->input('holder')) === '') {
            flash('error', 'Banco, número de cuenta y titular son obligatorios.');

            return redirect(url('/scloud/ajustes#bancos'));
        }
        $account = ($id = (int) $request->input('id')) ? BankAccount::findOrFail($id) : new BankAccount();
        $account->fill([
            'bank' => mb_substr($bank, 0, 150), 'account_type' => mb_substr(trim((string) $request->input('account_type')) ?: 'Corriente', 0, 50),
            'account_number' => mb_substr($num, 0, 60), 'holder' => mb_substr(trim((string) $request->input('holder')), 0, 150),
            'holder_id' => mb_substr(trim((string) $request->input('holder_id')), 0, 40) ?: null,
            'currency' => mb_substr(trim((string) $request->input('currency')) ?: 'DOP', 0, 10),
            'instructions' => trim((string) $request->input('instructions')) ?: null,
            'is_active' => (bool) $request->input('is_active'), 'sort_order' => (int) $request->input('sort_order'),
        ])->save();
        flash('success', 'Cuenta bancaria guardada.');

        return redirect(url('/scloud/ajustes#bancos'));
    }

    public function deleteBank(int $id)
    {
        BankAccount::where('id', $id)->delete();
        flash('success', 'Cuenta bancaria eliminada.');

        return redirect(url('/scloud/ajustes#bancos'));
    }

    public function runCron()
    {
        $stats = (new CronService())->run();
        $msg   = "Tarea ejecutada: {$stats['invoices']} factura(s) de renovación, {$stats['suspended']} suspensión(es), {$stats['terminated']} terminación(es), {$stats['domains']} dominio(s) revisado(s).";
        if ($stats['errors']) {
            flash('error', $msg . ' Errores: ' . implode(' · ', array_slice($stats['errors'], 0, 5)));
        } else {
            flash('success', $msg);
        }

        return redirect(url('/scloud/ajustes'));
    }

    /** Cron público: /scloud/cron?key=… */
    public function cron(Request $request)
    {
        if (!hash_equals(Settings::cronKey(), (string) $request->query('key'))) {
            return response()->json(['ok' => false, 'error' => 'Llave inválida.'], 403);
        }

        return response()->json(['ok' => true] + (new CronService())->run());
    }
}
