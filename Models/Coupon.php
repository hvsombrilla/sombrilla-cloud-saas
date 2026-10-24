<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

/** Cupón de descuento: se aplica a la primera factura de un pedido (no a las renovaciones). */
class Coupon extends Model
{
    use \Studio\Models\Traits\Auditable;
    protected string $auditEntity = 'scloud_coupons';
    protected array $auditLabels = ['code' => 'Código', 'description' => 'Descripción', 'type' => 'Tipo', 'value' => 'Valor', 'once_per_customer' => 'Una vez por cliente', 'starts_at' => 'Desde', 'expires_at' => 'Hasta', 'is_active' => 'Activo'];

    public function auditTitle(): string
    {
        return (string) $this->code;
    }

    protected $table = 'scloud_coupons';

    public const TYPES = ['percent' => 'Porcentaje', 'fixed' => 'Monto fijo'];

    protected $casts = [
        'value'             => 'float',
        'max_uses'          => 'integer',
        'once_per_customer' => 'boolean',
        'is_active'         => 'boolean',
        'starts_at'         => 'date',
        'expires_at'        => 'date',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** "20 %" o "RD$ 500.00". */
    public function valueLabel(): string
    {
        return $this->type === 'percent' ? rtrim(rtrim(number_format($this->value, 2), '0'), '.') . ' %' : scloud_money($this->value);
    }
}
