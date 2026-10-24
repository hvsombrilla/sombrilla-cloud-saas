<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class Order extends Model
{
    protected $table = 'scloud_orders';

    public const STATUSES = [
        'pending'   => ['Pendiente de pago', 'warn'],
        'paid'      => ['Pagado', 'info'],
        'active'    => ['Activo', 'ok'],
        'cancelled' => ['Cancelado', ''],
    ];

    protected $casts = ['total' => 'float'];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'order_id');
    }

    public static function nextCode(): string
    {
        do {
            $code = 'SC-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (self::where('code', $code)->exists());

        return $code;
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
