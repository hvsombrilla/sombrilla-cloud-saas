<?php

namespace SombrillaCloud\Models;

use ErpFacturacion\Models\Invoice;
use Studio\Models\Model;

class PaymentProof extends Model
{
    protected $table = 'scloud_payment_proofs';

    public const STATUSES = [
        'pending'  => ['En revisión', 'warn'],
        'approved' => ['Aprobado', 'ok'],
        'rejected' => ['Rechazado', 'bad'],
    ];

    protected $casts = [
        'amount'      => 'float',
        'paid_at'     => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? (string) $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }
}
