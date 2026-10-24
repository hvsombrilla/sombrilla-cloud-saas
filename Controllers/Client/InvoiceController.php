<?php

namespace SombrillaCloud\Controllers\Client;

use ErpFacturacion\Models\Invoice;
use Illuminate\Http\Request;
use SombrillaCloud\Models\BankAccount;
use SombrillaCloud\Models\InvoiceLink;
use SombrillaCloud\Models\PaymentProof;
use SombrillaCloud\Services\BillingService;
use SombrillaCloud\Support\Files;
use SombrillaCloud\Support\Mailer;

class InvoiceController
{
    public function index()
    {
        return scloud_view('client.invoices', ['invoices' => BillingService::invoicesFor(scloud_customer())]);
    }

    public function show(int $id)
    {
        $invoice = $this->own($id);

        return scloud_view('client.invoice', [
            'invoice' => $invoice->load('items'),
            'banks'   => BankAccount::active()->get(),
            'proofs'  => PaymentProof::where('invoice_id', $invoice->id)->where('customer_id', scloud_customer()->id)->orderByDesc('id')->get(),
            'link'    => InvoiceLink::where('invoice_id', $invoice->id)->first(),
        ]);
    }

    public function print(int $id)
    {
        $invoice = $this->own($id);

        return scloud_view('client.invoice-print', ['invoice' => $invoice->load('items'), 'customer' => scloud_customer()]);
    }

    public function uploadProof(Request $request, int $id)
    {
        $invoice  = $this->own($id);
        $customer = scloud_customer();
        $back     = '/cliente/facturas/' . $invoice->id;

        if (!in_array($invoice->status, ['draft', 'issued', 'partial', 'overdue'], true) || (float) $invoice->balance <= 0) {
            return scloud_back_with($back, ['Esta factura no tiene saldo pendiente.']);
        }
        if (PaymentProof::where('invoice_id', $invoice->id)->where('status', 'pending')->exists()) {
            return scloud_back_with($back, ['Ya tienes un comprobante en revisión para esta factura.']);
        }

        $errors = [];
        $amount = round((float) str_replace(',', '', (string) $request->input('amount')), 2);
        if ($amount <= 0) {
            $errors[] = 'Indica el monto transferido.';
        }
        $paidAt = (string) $request->input('paid_at');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt) || strtotime($paidAt) === false || $paidAt > date('Y-m-d')) {
            $errors[] = 'Indica la fecha de la transferencia.';
        }
        $reference = trim((string) $request->input('reference'));
        if ($reference === '') {
            $errors[] = 'Indica el número de referencia de la transferencia.';
        }
        $bankId = (int) $request->input('bank_account_id');
        if ($bankId && !BankAccount::where('id', $bankId)->exists()) {
            $bankId = 0;
        }
        $stored = Files::store($request->file('proof'), 'proofs', true);
        if (!$stored['ok']) {
            $errors[] = $stored['error'];
        }
        if ($errors) {
            return scloud_back_with($back, $errors, $request->all());
        }

        $proof = PaymentProof::create([
            'invoice_id'      => $invoice->id,
            'customer_id'     => $customer->id,
            'bank_account_id' => $bankId ?: null,
            'amount'          => min($amount, (float) $invoice->balance),
            'paid_at'         => $paidAt,
            'reference'       => mb_substr($reference, 0, 100),
            'file'            => $stored['path'],
            'notes'           => mb_substr(trim((string) $request->input('notes')), 0, 1000) ?: null,
            'status'          => 'pending',
        ]);

        Mailer::proofReceived($proof->fresh(['customer', 'invoice']));
        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_approve_payments', 'Comprobante por validar', [
                    'type' => 'info', 'body' => $customer->name . ' · ' . scloud_money($proof->amount) . ' · ' . $invoice->code,
                    'url' => url('/scloud/pagos'), 'icon' => 'fa fa-money-check', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }
        flash('success', 'Comprobante recibido. Validaremos tu pago en breve.');

        return redirect(url($back));
    }

    public function proofFile(int $id)
    {
        $proof = PaymentProof::where('id', $id)->where('customer_id', scloud_customer()->id)->first();
        if (!$proof) {
            scloud_not_found();
        }

        return Files::response($proof->file);
    }

    private function own(int $id): Invoice
    {
        $invoice = BillingService::invoicesFor(scloud_customer())->firstWhere('id', $id);
        if (!$invoice) {
            scloud_not_found();
        }

        return $invoice;
    }
}
