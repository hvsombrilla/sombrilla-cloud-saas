<?php

namespace SombrillaCloud\Models;

use ErpFacturacion\Models\Invoice;
use Studio\Models\Model;

class PaymentProof extends Model
{
    use \Studio\Models\Traits\Auditable;
    use AuditsStatus;
    protected string $auditEntity = 'scloud_payment_proofs';
    protected array $auditExclude = ['file'];
    protected array $auditLabels = ['amount' => 'Monto', 'paid_at' => 'Fecha de pago', 'reference' => 'Referencia', 'notes' => 'Notas', 'status' => 'Estado', 'review_notes' => 'Notas de revisión'];

    public function auditTitle(): string
    {
        return 'Comprobante #' . $this->getKey();
    }

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
