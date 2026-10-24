<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class Customer extends Model
{
    use \Studio\Models\Traits\Auditable;
    protected string $auditEntity = 'scloud_customers';
    protected array $auditExclude = ['password', 'reset_token', 'reset_expires_at', 'last_login_ip', 'last_login_at'];
    protected array $auditLabels = ['name' => 'Nombre', 'company' => 'Empresa', 'email' => 'Correo', 'phone' => 'Teléfono', 'tax_id' => 'Documento fiscal', 'address' => 'Dirección', 'city' => 'Ciudad', 'country' => 'País', 'status' => 'Estado', 'notes' => 'Notas'];

    protected $table = 'scloud_customers';

    protected $hidden = ['password', 'reset_token'];

    protected $casts = [
        'last_login_at'    => 'datetime',
        'reset_expires_at' => 'datetime',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'customer_id')->orderByDesc('id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id')->orderByDesc('id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'customer_id')->orderByDesc('id');
    }

    public function firstName(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?: (string) $this->name;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }

    public function checkPassword(string $password): bool
    {
        return password_verify($password, (string) $this->password);
    }

    public function setPassword(string $password): void
    {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }
}
