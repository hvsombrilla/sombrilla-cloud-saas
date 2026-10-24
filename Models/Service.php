<?php

namespace SombrillaCloud\Models;

use SombrillaCloud\Support\Cycles;
use Studio\AI\Secret;
use Studio\Models\Model;

class Service extends Model
{
    protected $table = 'scloud_services';

    protected $hidden = ['password'];

    public const STATUSES = [
        'pending'    => ['Pendiente', 'warn'],
        'active'     => ['Activo', 'ok'],
        'suspended'  => ['Suspendido', 'bad'],
        'terminated' => ['Terminado', ''],
        'cancelled'  => ['Cancelado', ''],
        'failed'     => ['Error al crear', 'bad'],
    ];

    protected $casts = [
        'amount'            => 'float',
        'registration_date' => 'date',
        'next_due_date'     => 'date',
        'cancel_requested'  => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function server()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function logs()
    {
        return $this->hasMany(ServiceLog::class, 'service_id')->orderByDesc('id');
    }

    public function plainPassword(): string
    {
        return Secret::decrypt($this->password);
    }

    public function setPlainPassword(string $password): void
    {
        $this->password = $password === '' ? null : Secret::encrypt($password);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? (string) $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }

    public function cycleLabel(): string
    {
        return Cycles::label($this->billing_cycle);
    }

    /** Nombre para listados: "Plan Pro — midominio.com". */
    public function title(): string
    {
        return trim(($this->plan->name ?? 'Servicio') . ($this->domain ? ' — ' . $this->domain : ''));
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['active', 'suspended'], true);
    }
}
