<?php

namespace SombrillaCloud\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Rutas del panel de clientes: requiere sesión de cliente (scloud_customer_id). */
class CustomerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $customer = scloud_customer();
        if (!$customer) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if ($request->isMethod('GET')) {
                $_SESSION['scloud_intended'] = $request->getRequestUri();
            }

            return redirect(url('/cliente/login'));
        }
        if ($customer->status !== 'active') {
            scloud_logout();
            flash('error', 'Tu cuenta está deshabilitada. Contáctanos para más información.');

            return redirect(url('/cliente/login'));
        }

        app('view')->share('customer', $customer);

        return $next($request);
    }
}
