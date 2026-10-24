<?php

namespace SombrillaCloud\Support;

use SombrillaCloud\Models\Plan;
use SombrillaCloud\Services\CouponService;

/** Carrito en sesión: lista de ['key', 'plan_id', 'cycle', 'mode', 'subdomain', 'domain', 'workspace', 'domain_price']. */
final class Cart
{
    private const KEY = 'scloud_cart';
    private const COUPON = 'scloud_cart_coupon';

    public static function items(): array
    {
        self::start();

        return array_values(is_array($_SESSION[self::KEY] ?? null) ? $_SESSION[self::KEY] : []);
    }

    /** @param array $data mode, subdomain, domain, workspace, domain_price (ya validados por OrderService::validateItem) */
    public static function add(int $planId, string $cycle, array $data): string
    {
        self::start();
        $key = bin2hex(random_bytes(6));
        $_SESSION[self::KEY][] = ['key' => $key, 'plan_id' => $planId, 'cycle' => $cycle] + array_intersect_key($data, array_flip(['mode', 'subdomain', 'domain', 'workspace', 'domain_price']));

        return $key;
    }

    public static function remove(string $key): void
    {
        self::start();
        $_SESSION[self::KEY] = array_values(array_filter(self::items(), fn($i) => $i['key'] !== $key));
    }

    public static function clear(): void
    {
        self::start();
        unset($_SESSION[self::KEY], $_SESSION[self::COUPON]);
    }

    /** Código del cupón aplicado al carrito, o null. */
    public static function coupon(): ?string
    {
        self::start();

        return $_SESSION[self::COUPON] ?? null;
    }

    public static function setCoupon(?string $code): void
    {
        self::start();
        $code === null ? self::forgetCoupon() : $_SESSION[self::COUPON] = $code;
    }

    private static function forgetCoupon(): void
    {
        unset($_SESSION[self::COUPON]);
    }

    public static function count(): int
    {
        return count(self::items());
    }

    /** Ítems con su plan y precios; descarta los que ya no son válidos. */
    public static function detailed(): array
    {
        $items = self::items();
        $plans = Plan::whereIn('id', array_column($items, 'plan_id') ?: [0])->where('is_active', true)->get()->keyBy('id');
        $out   = [];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $plan  = $plans[$item['plan_id']] ?? null;
            $price = $plan?->price((string) $item['cycle']);
            if (!$plan || $price === null) {
                continue;
            }
            $setup     = (float) $plan->setup_fee;
            $domain    = (float) ($item['domain_price'] ?? 0);
            $subtotal += $price + $setup + $domain;
            $out[]     = $item + ['plan' => $plan, 'price' => $price, 'setup' => $setup, 'domain_charge' => $domain];
        }

        // Cupón: se revalida en cada vista; si dejó de ser válido se quita (el motivo queda en 'coupon_error').
        $coupon      = null;
        $couponError = null;
        if ($out && ($code = self::coupon())) {
            $model       = CouponService::find($code);
            $couponError = $model ? CouponService::error($model, array_column($out, 'plan_id'), scloud_customer()) : 'Ese cupón no existe.';
            if ($couponError) {
                self::forgetCoupon();
            } else {
                $alloc  = CouponService::allocate($model, array_map(fn($i) => ['plan_id' => $i['plan']->id, 'price' => $i['price'], 'setup' => $i['setup']], $out));
                $coupon = ['code' => $model->code, 'label' => $model->valueLabel(), 'discount' => $alloc['discount']];
            }
        }

        $discount = $coupon['discount'] ?? 0.0;
        $totals   = \SombrillaCloud\Services\BillingService::totals($subtotal - $discount);
        $totals['subtotal'] = round($subtotal, 2);
        $totals['discount'] = $discount;

        return ['items' => $out, 'totals' => $totals, 'coupon' => $coupon, 'coupon_error' => $couponError];
    }

    private static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
