<?php

namespace SombrillaCloud\Controllers\Site;

use Illuminate\Http\Request;
use SombrillaCloud\Controllers\Client\AuthController;
use SombrillaCloud\Models\BankAccount;
use SombrillaCloud\Services\OrderService;
use SombrillaCloud\Support\Cart;

class CheckoutController
{
    public function show()
    {
        $cart = Cart::detailed();
        if (!$cart['items']) {
            return redirect(url('/planes'));
        }

        return scloud_view('site.checkout', $cart + [
            'customer' => scloud_customer(),
            'banks'    => BankAccount::active()->get(),
        ]);
    }

    public function place(Request $request)
    {
        $cart = Cart::detailed();
        if (!$cart['items']) {
            return redirect(url('/planes'));
        }
        if (!$request->input('accept_terms')) {
            return scloud_back_with('/checkout', ['Debes aceptar los términos del servicio.'], $request->all());
        }

        // Cliente: sesión iniciada, inicio de sesión o registro en el mismo formulario.
        $customer = scloud_customer();
        if (!$customer) {
            $auth = new AuthController();
            $res  = $request->input('account') === 'login'
                ? $auth->attempt((string) $request->input('login_email'), (string) $request->input('login_password'))
                : $auth->createCustomer($request->all());
            if (!$res['ok']) {
                return scloud_back_with('/checkout', $res['errors'], $request->all());
            }
            $customer = $res['customer'];
            scloud_login($customer);
        }

        $items = array_map(fn($i) => array_intersect_key($i, array_flip(['plan_id', 'cycle', 'mode', 'subdomain', 'domain', 'workspace'])), $cart['items']);
        $res   = OrderService::place($customer, $items, (string) $request->input('notes', ''), $request->ip());
        if (!$res['ok']) {
            return scloud_back_with('/checkout', $res['errors'], $request->all());
        }

        Cart::clear();
        flash('success', '¡Pedido recibido! Te enviamos los datos para la transferencia.');

        return redirect(url('/cliente/pedidos/' . $res['order']->code));
    }
}
