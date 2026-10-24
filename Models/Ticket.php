<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class Ticket extends Model
{
    protected $table = 'scloud_tickets';

    public const DEPARTMENTS = [
        'support' => 'Soporte técnico',
        'billing' => 'Facturación y pagos',
        'sales'   => 'Ventas',
    ];

    public const PRIORITIES = [
        'low'    => ['Baja', ''],
        'medium' => ['Media', 'info'],
        'high'   => ['Alta', 'bad'],
    ];

    public const STATUSES = [
        'open'           => ['Abierto', 'warn'],
        'answered'       => ['Respondido', 'ok'],
        'customer_reply' => ['Respuesta del cliente', 'warn'],
        'on_hold'        => ['En espera', 'info'],
        'closed'         => ['Cerrado', ''],
    ];

    protected $casts = ['last_reply_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('id');
    }

    public static function nextCode(): string
    {
        do {
            $code = 'TK-' . random_int(100000, 999999);
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

    public function departmentLabel(): string
    {
        return self::DEPARTMENTS[$this->department] ?? (string) $this->department;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority][0] ?? (string) $this->priority;
    }

    public function priorityTone(): string
    {
        return self::PRIORITIES[$this->priority][1] ?? '';
    }

    /** Pendiente de respuesta del equipo. */
    public function awaitingStaff(): bool
    {
        return in_array($this->status, ['open', 'customer_reply'], true);
    }
}
