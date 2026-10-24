<?php

namespace SombrillaCloud\Controllers\Site;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Services\OrderService;
use SombrillaCloud\Support\Cart;

class CartController
{
    /** Configurar un plan antes de agregarlo: ciclo y dominio. */
    public function configure(Request $request, string $slug)
    {
        $plan = Plan::where('slug', $slug)->where('is_active', true)->first();
        if (!$plan || !$plan->cycles()) {
            scloud_not_found();
        }
        $cycle = (string) $request->query('ciclo', '');

        return scloud_view('site.configure', [
            'plan'  => $plan,
            'cycle' => $plan->price($cycle) !== null ? $cycle : $plan->defaultCycle(),
        ]);
    }

    public function add(Request $request)
    {
        $planId = (int) $request->input('plan_id');
        $plan   = Plan::find($planId);
        $back   = $plan ? '/contratar/' . $plan->slug : '/planes';

        $res = OrderService::validateItem([
            'plan_id' => $planId, 'cycle' => (string) $request->input('cycle'), 'domain' => (string) $request->input('domain'),
        ]);
        if (!$res['ok']) {
            return scloud_back_with($back, [$res['error']], $request->all());
        }
        foreach (Cart::items() as $item) {
            if ($res['domain'] && ($item['domain'] ?? null) === $res['domain']) {
                return scloud_back_with($back, ["El dominio {$res['domain']} ya está en tu carrito."], $request->all());
            }
        }

        Cart::add($planId, (string) $request->input('cycle'), $res['domain']);

        return redirect(url('/carrito'));
    }

    public function index()
    {
        return scloud_view('site.cart', Cart::detailed());
    }

    public function remove(string $key)
    {
        Cart::remove($key);

        return redirect(url('/carrito'));
    }
}
