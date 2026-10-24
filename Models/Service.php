<?php

namespace SombrillaCloud\Models;

use SombrillaCloud\Support\Cycles;
use Studio\Models\Model;

class Service extends Model
{
    protected $table = 'scloud_services';

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
        'domain_price'      => 'float',
        'domain_cost_usd'   => 'float',
        'domain_expires_at' => 'date',
        'provisioned_at'    => 'datetime',
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

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function logs()
    {
        return $this->hasMany(ServiceLog::class, 'service_id')->orderByDesc('id');
    }

    public const DOMAIN_STATUSES = [
        'registering' => ['Registrando dominio', 'warn'],
        'dns_pending' => ['Esperando DNS', 'warn'],
        'connected'   => ['Conectado', 'ok'],
        'failed'      => ['Error con el dominio', 'bad'],
    ];

    public const DOMAIN_MODES = ['subdomain' => 'Subdominio', 'buy' => 'Dominio comprado', 'own' => 'Dominio propio'];

    /** Host canónico de la instancia: {subdominio}.sombrilla.cloud. */
    public function host(): ?string
    {
        return $this->subdomain ? \SombrillaCloud\Support\Tenancy::host($this->subdomain) : null;
    }

    /** URL pública de la instancia (el dominio propio si ya está conectado; si no, el subdominio). */
    public function publicUrl(): ?string
    {
        if ($this->domain && $this->domain_status === 'connected') {
            return 'https://' . $this->domain;
        }

        return $this->subdomain ? \SombrillaCloud\Support\Tenancy::url($this->subdomain) : null;
    }

    public function domainStatusLabel(): string
    {
        return self::DOMAIN_STATUSES[$this->domain_status][0] ?? '';
    }

    public function domainStatusTone(): string
    {
        return self::DOMAIN_STATUSES[$this->domain_status][1] ?? '';
    }

    public function modeLabel(): string
    {
        return self::DOMAIN_MODES[$this->domain_mode] ?? 'Subdominio';
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

    /** Nombre para listados: "Plan Pro — acme.sombrilla.cloud". */
    public function title(): string
    {
        return trim(($this->plan->name ?? 'Servicio') . ($this->host() ? ' — ' . $this->host() : ($this->domain ? ' — ' . $this->domain : '')));
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['active', 'suspended'], true);
    }
}
