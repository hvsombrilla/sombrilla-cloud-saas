<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class BankAccount extends Model
{
    protected $table = 'scloud_bank_accounts';

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
