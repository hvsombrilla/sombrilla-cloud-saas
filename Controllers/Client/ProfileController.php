<?php

namespace SombrillaCloud\Controllers\Client;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Services\BillingService;

class ProfileController
{
    public function edit()
    {
        return scloud_view('client.profile');
    }

    public function update(Request $request)
    {
        $customer = scloud_customer();
        $name     = trim((string) $request->input('name'));
        $email    = strtolower(trim((string) $request->input('email')));
        $errors   = [];
        if (mb_strlen($name) < 3) {
            $errors[] = 'Escribe tu nombre completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Escribe un correo válido.';
        } elseif (Customer::where('email', $email)->where('id', '!=', $customer->id)->exists()) {
            $errors[] = 'Ese correo ya está en uso por otra cuenta.';
        }
        if ($errors) {
            return scloud_back_with('/cliente/perfil', $errors, $request->all());
        }

        $field = fn(string $k, int $max) => ($v = trim((string) $request->input($k))) === '' ? null : mb_substr($v, 0, $max);
        $customer->fill([
            'name' => mb_substr($name, 0, 150), 'email' => $email, 'company' => $field('company', 150), 'phone' => $field('phone', 50),
            'tax_id' => $field('tax_id', 40), 'address' => $field('address', 500), 'city' => $field('city', 100), 'country' => $field('country', 100),
        ])->save();
        BillingService::syncContact($customer);
        flash('success', 'Perfil actualizado.');

        return redirect(url('/cliente/perfil'));
    }

    public function password(Request $request)
    {
        $customer = scloud_customer();
        if (!$customer->checkPassword((string) $request->input('current_password'))) {
            return scloud_back_with('/cliente/perfil', ['La contraseña actual no es correcta.']);
        }
        if ($error = (new AuthController())->passwordError((string) $request->input('password'), (string) $request->input('password_confirmation'))) {
            return scloud_back_with('/cliente/perfil', [$error]);
        }
        $customer->setPassword((string) $request->input('password'));
        $customer->save();
        flash('success', 'Contraseña actualizada.');

        return redirect(url('/cliente/perfil'));
    }
}
