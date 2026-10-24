<?php

namespace SombrillaCloud\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Models\Order;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Support\Cycles;
use SombrillaCloud\Support\Mailer;

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
        if ($domain === null) {
            return 'Escribe un dominio válido (por ejemplo: miempresa.com).';
        }
        $taken = Service::where('domain', $domain)
            ->whereIn('status', ['pending', 'active', 'suspended', 'failed'])
            ->when($ignoreServiceId, fn($q) => $q->where('id', '!=', $ignoreServiceId))
            ->exists();

        return $taken ? "El dominio {$domain} ya tiene un servicio con nosotros." : null;
    }

    /** Valida un ítem del carrito. @return array{ok: bool, error?: string, plan?: Plan, price?: float, domain?: ?string} */
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
        $domain = null;
        if ($plan->requires_domain) {
            $domain = self::normalizeDomain((string) ($item['domain'] ?? ''));
            if ($error = self::domainError($domain)) {
                return ['ok' => false, 'error' => $error];
            }
        }

        return ['ok' => true, 'plan' => $plan, 'price' => $price, 'domain' => $domain];
    }

    /**
     * Crea el pedido, sus servicios (pendientes) y la factura en el ERP.
     *
     * @param array $items [['plan_id', 'cycle', 'domain'], ...]
     * @return array{ok: bool, order?: Order, errors?: string[]}
     */
    public static function place(Customer $customer, array $items, string $notes = '', ?string $ip = null): array
    {
        if (!$items) {
            return ['ok' => false, 'errors' => ['Tu carrito está vacío.']];
        }

        $valid   = [];
        $errors  = [];
        $domains = [];
        foreach ($items as $item) {
            $res = self::validateItem($item);
            if (!$res['ok']) {
                $errors[] = $res['error'];
                continue;
            }
            if ($res['domain'] && in_array($res['domain'], $domains, true)) {
                $errors[] = "El dominio {$res['domain']} está repetido en el carrito.";
                continue;
            }
            $domains[] = $res['domain'];
            $valid[]   = $res + ['cycle' => (string) $item['cycle']];
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $order = Capsule::connection()->transaction(function () use ($customer, $valid, $notes, $ip) {
            $order = Order::create([
                'code'           => Order::nextCode(),
                'customer_id'    => $customer->id,
                'status'         => 'pending',
                'payment_method' => 'bank_transfer',
                'notes'          => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
                'ip'             => $ip,
            ]);
            foreach ($valid as $v) {
                Service::create([
                    'customer_id'   => $customer->id,
                    'plan_id'       => $v['plan']->id,
                    'order_id'      => $order->id,
                    'server_id'     => $v['plan']->server_id,
                    'domain'        => $v['domain'],
                    'whm_package'   => $v['plan']->whm_package,
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
        foreach ($valid as $v) {
            $end = Cycles::addTo(date('Y-m-d'), $v['cycle'])->format('d/m/Y');
            $lines[] = [
                'product_id'  => $v['plan']->product_id,
                'description' => "Hosting {$v['plan']->name}" . ($v['domain'] ? " — {$v['domain']}" : '') . ' · ' . Cycles::label($v['cycle']) . " ({$start} - {$end})",
                'quantity'    => 1,
                'unit_price'  => $v['price'],
            ];
            $subtotal += $v['price'];
            if ($v['plan']->setup_fee > 0) {
                $lines[] = ['description' => "Instalación {$v['plan']->name}", 'quantity' => 1, 'unit_price' => $v['plan']->setup_fee];
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
