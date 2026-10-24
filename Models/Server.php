<?php

namespace SombrillaCloud\Models;

use Studio\AI\Secret;
use Studio\Models\Model;

class Server extends Model
{
    protected $table = 'scloud_servers';

    protected $hidden = ['api_token'];

    protected $casts = [
        'verify_ssl'   => 'boolean',
        'is_active'    => 'boolean',
        'port'         => 'integer',
        'max_accounts' => 'integer',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'server_id');
    }

    public function token(): string
    {
        return Secret::decrypt($this->api_token);
    }

    public function setToken(string $token): void
    {
        $this->api_token = $token === '' ? null : Secret::encrypt($token);
    }

    public function hasToken(): bool
    {
        return !empty($this->api_token);
    }

    /** Cuentas vivas en este servidor (las terminadas no cuentan). */
    public function accountCount(): int
    {
        return Service::where('server_id', $this->id)->whereIn('status', ['active', 'suspended'])->count();
    }

    public function isFull(): bool
    {
        return $this->max_accounts > 0 && $this->accountCount() >= $this->max_accounts;
    }

    public function whmUrl(): string
    {
        return 'https://' . $this->hostname . ':' . ($this->port ?: 2087) . '/';
    }

    public function cpanelUrl(): string
    {
        return 'https://' . $this->hostname . ':2083/';
    }
}
