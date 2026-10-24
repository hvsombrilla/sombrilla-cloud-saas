<?php

namespace SombrillaCloud\Models;

use SombrillaCloud\Support\Cycles;
use Studio\Models\Model;

class Plan extends Model
{
    protected $table = 'scloud_plans';

    protected $casts = [
        'setup_fee'       => 'float',
        'requires_domain' => 'boolean',
        'is_featured'     => 'boolean',
        'is_active'       => 'boolean',
        'is_hidden'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_active', true)->where('is_hidden', false)->orderBy('sort_order')->orderBy('id');
    }

    /** Características, una por línea. */
    public function featureList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->features) ?: [])));
    }

    /** Precio del ciclo, o null si el plan no lo ofrece. */
    public function price(string $cycle): ?float
    {
        if (!Cycles::valid($cycle)) {
            return null;
        }
        $v = $this->getAttribute('price_' . $cycle);

        return $v === null || $v === '' ? null : (float) $v;
    }

    /** Ciclos ofrecidos: clave => precio. */
    public function cycles(): array
    {
        $out = [];
        foreach (array_keys(Cycles::ALL) as $cycle) {
            $price = $this->price($cycle);
            if ($price !== null) {
                $out[$cycle] = $price;
            }
        }

        return $out;
    }

    /** Ciclo por defecto para mostrar: el mensual si existe, si no el primero ofrecido. */
    public function defaultCycle(): ?string
    {
        $cycles = $this->cycles();

        return isset($cycles['monthly']) ? 'monthly' : (array_key_first($cycles) ?: null);
    }

    /** Precio mensual equivalente de un ciclo (para mostrar "desde RD$ X/mes"). */
    public function monthlyEquivalent(string $cycle): ?float
    {
        $price = $this->price($cycle);

        return $price === null ? null : round($price / Cycles::months($cycle), 2);
    }

    /** Ahorro (%) de un ciclo frente a pagar mes a mes. */
    public function savings(string $cycle): int
    {
        $monthly = $this->price('monthly');
        $price   = $this->price($cycle);
        if (!$monthly || $price === null || $cycle === 'monthly') {
            return 0;
        }
        $full = $monthly * Cycles::months($cycle);

        return $full > 0 ? (int) max(0, round((1 - $price / $full) * 100)) : 0;
    }
}
