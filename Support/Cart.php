<?php

namespace SombrillaCloud\Support;

use SombrillaCloud\Models\Plan;

/** Carrito en sesión: lista de ['key', 'plan_id', 'cycle', 'mode', 'subdomain', 'domain', 'workspace', 'domain_price']. */
final class Cart
{
    private const KEY = 'scloud_cart';

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
        unset($_SESSION[self::KEY]);
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

        return ['items' => $out, 'totals' => \SombrillaCloud\Services\BillingService::totals($subtotal)];
    }

    private static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
