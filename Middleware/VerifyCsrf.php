<?php

namespace SombrillaCloud\Middleware;

use Closure;
use Illuminate\Http\Request;

/** CSRF para los formularios públicos, del panel de clientes y de administración (token de scloud_csrf()). */
class VerifyCsrf
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = (string) ($request->input('_token') ?: $request->header('X-CSRF-Token', ''));
            if ($token === '' || !ss_verify_csrf($token)) {
                flash('error', 'Tu sesión expiró. Vuelve a intentarlo.');

                return redirect($_SERVER['HTTP_REFERER'] ?? url('/'));
            }
        }

        return $next($request);
    }
}
