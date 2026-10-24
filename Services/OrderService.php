<?php

namespace SombrillaCloud\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Support\Cycles;
use SombrillaCloud\Support\Mailer;
use SombrillaCloud\Support\Tenancy;

class OrderService
{
    /** Normaliza un dominio ("https://www.Ejemplo.com/" → "ejemplo.com"); null si no es válido. */
    public static function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', (string) $domain);
        $domain = preg_replace('/^www\./', '', (string) $domain);
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', (string) $domain)) {
            $domain = idn_to_ascii((string) $domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        return preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', (string) $domain) ? $domain : null;
    }

    /** Motivo por el que un dominio no puede usarse, o null. */
    public static function domainError(?string $domain, ?int $ignoreServiceId = null): ?string
    {
        return DomainService::domainError($domain, $ignoreServiceId);
    }

    /**
     * Valida un ítem del carrito: plan, ciclo, nombre del workspace y la opción de dominio
     * (subdominio elegido, dominio propio o dominio comprado). Los precios se recalculan aquí, nunca vienen del cliente.
     *
     * @return array{ok: bool, error?: string, plan?: Plan, price?: float, mode?: string, subdomain?: string, domain?: ?string, workspace?: string, domain_price?: float, domain_cost?: ?float}
     */
    public static function validateItem(array $item): array
    {
        $plan = Plan::where('id', (int) ($item['plan_id'] ?? 0))->where('is_active', true)->first();
        if (!$plan) {
            return ['ok' => false, 'error' => 'El plan seleccionado ya no está disponible.'];
        }
        $cycle = (string) ($item['cycle'] ?? '');
        $price = $plan->price($cycle);
        if ($price === null) {
            return ['ok' => false, 'error' => "El plan {$plan->name} no se ofrece con ciclo " . Cycles::label($cycle) . '.'];
        }

        $workspace = trim(preg_replace('/\s+/', ' ', (string) ($item['workspace'] ?? '')));
        if ($error = \Studio\Services\Workspace::validateName($workspace)) {
            return ['ok' => false, 'error' => str_replace('espacio de trabajo', 'workspace', $error)];
        }

        $mode = (string) ($item['mode'] ?? 'subdomain');
        if (!DomainService::modeEnabled($mode)) {
            return ['ok' => false, 'error' => 'Esa opción de dominio no está disponible.'];
        }

        $subdomain   = strtolower(trim((string) ($item['subdomain'] ?? '')));
        $domain      = null;
        $domainPrice = 0.0;
        $domainCost  = null;

        if ($mode === 'subdomain') {
            if ($error = DomainService::subdomainError($subdomain)) {
                return ['ok' => false, 'error' => $error];
            }
        } else {
            $domain = self::normalizeDomain((string) ($item['domain'] ?? ''));
            if ($error = DomainService::domainError($domain)) {
                return ['ok' => false, 'error' => $error];
            }
            // El subdominio de una instancia con dominio propio/comprado se genera con el nombre del workspace.
            if ($subdomain === '' || DomainService::subdomainError($subdomain) !== null) {
                $subdomain = DomainService::generateSlug($workspace);
            }
            if ($mode === 'buy') {
                try {
                    $check = Cloudflare::checkOne($domain);
                } catch (CloudflareException $e) {
                    return ['ok' => false, 'error' => 'No pudimos verificar la disponibilidad del dominio en este momento. Inténtalo de nuevo en unos minutos.'];
                }
                if (!$check || !$check['registrable']) {
                    return ['ok' => false, 'error' => "El dominio {$domain} no está disponible para registrar."];
                }
                $domainPrice = (float) $check['price'];
                $domainCost  = $check['cost'];
            }
        }

        return [
            'ok' => true, 'plan' => $plan, 'price' => $price, 'mode' => $mode, 'subdomain' => $subdomain, 'domain' => $domain,
            'workspace' => $workspace, 'domain_price' => $domainPrice, 'domain_cost' => $domainCost,
        ];
    }

    /**
     * Crea el pedido, sus servicios (pendientes) y la factura en el ERP.
     *
     * @param array $items [['plan_id', 'cycle', 'domain'], ...]
     * @return array{ok: bool, order?: Order, errors?: string[]}
     */
    public static function place(Customer $customer, array $items, string $notes = '', ?string $ip = null, ?string $couponCode = null): array
    {
        if (!$items) {
            return ['ok' => false, 'errors' => ['Tu carrito está vacío.']];
        }

        $valid = [];
        $errors = [];
        $seen   = [];
        foreach ($items as $item) {
            $res = self::validateItem($item);
            if (!$res['ok']) {
                $errors[] = $res['error'];
                continue;
            }
            // Dos ítems del mismo pedido no pueden compartir subdominio ni dominio.
            foreach (array_filter([$res['subdomain'], $res['domain']]) as $name) {
                if (in_array($name, $seen, true)) {
                    $errors[] = "«{$name}» está repetido en el carrito.";
                    continue 2;
                }
                $seen[] = $name;
            }
            $valid[] = $res + ['cycle' => (string) $item['cycle']];
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Cupón: se revalida y el descuento se recalcula aquí, nunca viene del carrito.
        $coupon   = null;
        $discount = ['discount' => 0.0, 'lines' => []];
        if ($couponCode !== null && $couponCode !== '') {
            $coupon = CouponService::find($couponCode);
            $error  = $coupon ? CouponService::error($coupon, array_map(fn($v) => $v['plan']->id, $valid), $customer) : 'Ese cupón no existe.';
            if ($error) {
                return ['ok' => false, 'errors' => [$error]];
            }
            $discount = CouponService::allocate($coupon, array_map(fn($v) => ['plan_id' => $v['plan']->id, 'price' => $v['price'], 'setup' => (float) $v['plan']->setup_fee], $valid));
        }

        $order = Capsule::connection()->transaction(function () use ($customer, $valid, $notes, $ip, $coupon, $discount) {
            $order = Order::create([
                'code'           => Order::nextCode(),
                'customer_id'    => $customer->id,
                'status'         => 'pending',
                'payment_method' => 'bank_transfer',
                'notes'          => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
                'ip'             => $ip,
                'coupon_id'      => $coupon?->id,
                'coupon_code'    => $coupon?->code,
                'discount'       => $discount['discount'],
            ]);
            foreach ($valid as $v) {
                Service::create([
                    'customer_id'   => $customer->id,
                    'plan_id'       => $v['plan']->id,
                    'order_id'      => $order->id,
                    'subdomain'      => $v['subdomain'],
                    'domain_mode'    => $v['mode'],
                    'domain'         => $v['domain'],
                    'domain_status'  => null,
                    'domain_price'   => $v['domain_price'],
                    'domain_cost_usd' => $v['domain_cost'],
                    'workspace_name' => $v['workspace'],
                    'billing_cycle' => $v['cycle'],
                    'amount'        => $v['price'],
                    'status'        => 'pending',
                ]);
            }

            return $order;
        });

        // Factura en el ERP: una línea por servicio + cargos de instalación.
        $lines    = [];
        $subtotal = 0.0;
        $start    = date('d/m/Y');
        foreach ($valid as $i => $v) {
            $end = Cycles::addTo(date('Y-m-d'), $v['cycle'])->format('d/m/Y');
            $lines[] = [
                'product_id'  => $v['plan']->product_id,
                'description' => "Instancia {$v['plan']->name} — " . Tenancy::host($v['subdomain']) . ' · ' . Cycles::label($v['cycle']) . " ({$start} - {$end})",
                'quantity'    => 1,
                'unit_price'  => $v['price'],
                'discount'    => $discount['lines'][$i]['price'] ?? 0,
            ];
            $subtotal += $v['price'];
            if ($v['domain_price'] > 0) {
                $lines[] = ['description' => "Registro del dominio {$v['domain']} (1 año)", 'quantity' => 1, 'unit_price' => $v['domain_price']];
                $subtotal += $v['domain_price'];
            }
            if ($v['plan']->setup_fee > 0) {
                $lines[] = ['description' => "Instalación {$v['plan']->name}", 'quantity' => 1, 'unit_price' => $v['plan']->setup_fee, 'discount' => $discount['lines'][$i]['setup'] ?? 0];
                $subtotal += $v['plan']->setup_fee;
            }
        }

        $res = BillingService::createInvoice($customer, $lines, [
            'kind' => 'order', 'order_id' => $order->id, 'notes' => "Pedido {$order->code} · Sombrilla Cloud",
        ]);
        if (!$res['ok']) {
            // Sin factura no hay pedido: se revierte.
            Service::where('order_id', $order->id)->delete();
            $order->delete();

            return ['ok' => false, 'errors' => [$res['error']]];
        }

        $order->update(['invoice_id' => $res['invoice']->id, 'total' => $res['invoice']->total]);

        if ($coupon) {
            Capsule::table('scloud_coupon_redemptions')->insert([
                'coupon_id' => $coupon->id, 'customer_id' => $customer->id, 'order_id' => $order->id,
                'amount' => $discount['discount'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Mailer::orderPlaced($order->fresh(['customer', 'services.plan']), $res['invoice']);
        if (function_exists('ss_notify')) {
            try {
                ss_notify('permission:scloud_view', 'Nuevo pedido ' . $order->code, [
                    'type' => 'info', 'body' => $customer->name . ' · ' . scloud_money($order->total),
                    'url' => url('/scloud/pedidos/' . $order->id), 'icon' => 'fa fa-shopping-cart', 'source' => 'sombrilla-cloud',
                ]);
            } catch (\Throwable) {
            }
        }
        do_action('scloud_order_placed', ['order_id' => $order->id]);

        return ['ok' => true, 'order' => $order->fresh()];
    }
}
