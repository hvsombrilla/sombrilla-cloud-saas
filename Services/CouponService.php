<?php

namespace SombrillaCloud\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use SombrillaCloud\Models\Coupon;
use SombrillaCloud\Models\Customer;

/**
 * Reglas de los cupones, compartidas por el carrito y el pedido (los importes nunca vienen del cliente).
 * El descuento se reparte en las líneas del plan y de la instalación; el dominio no se descuenta.
 */
final class CouponService
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)));
    }

    public static function find(string $code): ?Coupon
    {
        $code = self::normalize($code);

        return $code === '' ? null : Coupon::where('code', $code)->first();
    }

    /** Usos vigentes: los de pedidos cancelados no cuentan. */
    public static function uses(Coupon $coupon, ?int $customerId = null): int
    {
        return Capsule::table('scloud_coupon_redemptions as r')
            ->join('scloud_orders as o', 'o.id', '=', 'r.order_id')
            ->where('r.coupon_id', $coupon->id)
            ->where('o.status', '!=', 'cancelled')
            ->when($customerId, fn($q) => $q->where('r.customer_id', $customerId))
            ->count();
    }

    /**
     * Motivo por el que el cupón no puede usarse con estos planes, o null.
     *
     * @param int[] $planIds planes del carrito
     */
    public static function error(Coupon $coupon, array $planIds, ?Customer $customer = null): ?string
    {
        $today = date('Y-m-d');
        if (!$coupon->is_active) {
            return 'Ese cupón no está disponible.';
        }
        if ($coupon->starts_at && $coupon->starts_at->format('Y-m-d') > $today) {
            return 'Ese cupón todavía no está vigente.';
        }
        if ($coupon->expires_at && $coupon->expires_at->format('Y-m-d') < $today) {
            return 'Ese cupón ya venció.';
        }
        if ($coupon->plan_id && !in_array((int) $coupon->plan_id, array_map('intval', $planIds), true)) {
            return 'Ese cupón no aplica a los planes de tu carrito.';
        }
        if ($coupon->max_uses && self::uses($coupon) >= $coupon->max_uses) {
            return 'Ese cupón ya alcanzó su límite de usos.';
        }
        if ($customer && $coupon->once_per_customer && self::uses($coupon, $customer->id) > 0) {
            return 'Ya usaste este cupón.';
        }

        return null;
    }

    /**
     * Reparte el descuento entre las líneas elegibles.
     *
     * @param array $lines [['plan_id' => int, 'price' => float, 'setup' => float], ...]
     * @return array{discount: float, lines: array<int, array{price: float, setup: float}>} descuento por línea (misma posición)
     */
    public static function allocate(Coupon $coupon, array $lines): array
    {
        $out       = [];
        $remaining = $coupon->type === 'fixed' ? (float) $coupon->value : null;
        $total     = 0.0;

        foreach ($lines as $i => $line) {
            $out[$i] = ['price' => 0.0, 'setup' => 0.0];
            if ($coupon->plan_id && (int) $coupon->plan_id !== (int) $line['plan_id']) {
                continue;
            }
            foreach (['price', 'setup'] as $part) {
                $amount = max(0.0, (float) ($line[$part] ?? 0));
                if ($remaining === null) {
                    $d = round($amount * min(100.0, (float) $coupon->value) / 100, 2);
                } else {
                    $d = round(min($remaining, $amount), 2);
                    $remaining -= $d;
                }
                $out[$i][$part] = $d;
                $total += $d;
            }
        }

        return ['discount' => round($total, 2), 'lines' => $out];
    }
}
