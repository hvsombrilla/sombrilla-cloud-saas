<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class BankAccount extends Model
{
    use \Studio\Models\Traits\Auditable;
    protected string $auditEntity = 'scloud_bank_accounts';
    protected array $auditLabels = ['bank' => 'Banco', 'account_type' => 'Tipo de cuenta', 'account_number' => 'Número de cuenta', 'holder' => 'Titular', 'holder_id' => 'Documento del titular', 'currency' => 'Moneda', 'instructions' => 'Instrucciones', 'is_active' => 'Activa', 'sort_order' => 'Orden'];

    public function auditTitle(): string
    {
        return $this->bank . ' · ' . $this->account_number;
    }

    protected $table = 'scloud_bank_accounts';

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
