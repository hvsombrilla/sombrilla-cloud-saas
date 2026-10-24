<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\PaymentProof;
use SombrillaCloud\Services\BillingService;
use SombrillaCloud\Support\Files;

class PaymentController
{
    public function index(Request $request)
    {
        $status = in_array($request->query('estado'), ['pending', 'approved', 'rejected', 'all'], true) ? $request->query('estado') : 'pending';
        $proofs = PaymentProof::with('customer', 'invoice', 'bankAccount')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('id')->paginate(25)->appends($request->query());

        return scloud_view('admin.payments', ['proofs' => $proofs, 'status' => $status]);
    }

    public function file(int $id)
    {
        return Files::response(PaymentProof::findOrFail($id)->file);
    }

    public function approve(Request $request, int $id)
    {
        $proof  = PaymentProof::findOrFail($id);
        $amount = (float) str_replace(',', '', (string) ($request->input('amount') ?: $proof->amount));
        $res    = BillingService::approveProof($proof, $amount, ss_session_user_id() ?: null, trim((string) $request->input('notes')));
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Pago aprobado y registrado en el ERP.' : $res['error']);

        return redirect(url('/scloud/pagos'));
    }

    public function reject(Request $request, int $id)
    {
        $reason = trim((string) $request->input('reason'));
        if ($reason === '') {
            flash('error', 'Indica el motivo del rechazo para que el cliente lo vea.');

            return redirect(url('/scloud/pagos'));
        }
        $res = BillingService::rejectProof(PaymentProof::findOrFail($id), $reason, ss_session_user_id() ?: null);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Comprobante rechazado; se notificó al cliente.' : $res['error']);

        return redirect(url('/scloud/pagos'));
    }
}
