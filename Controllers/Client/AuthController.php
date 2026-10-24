<?php

namespace SombrillaCloud\Controllers\Client;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Support\Mailer;

class AuthController
{
    private const MAX_ATTEMPTS = 6;
    private const LOCK_SECONDS = 600;

    public function showLogin()
    {
        if (scloud_customer()) {
            return redirect(url('/cliente'));
        }

        return scloud_view('client.login');
    }

    public function login(Request $request)
    {
        $res = $this->attempt((string) $request->input('email'), (string) $request->input('password'));
        if (!$res['ok']) {
            return scloud_back_with('/cliente/login', $res['errors'], ['email' => $request->input('email')]);
        }
        scloud_login($res['customer']);

        $to = $_SESSION['scloud_intended'] ?? '/cliente';
        unset($_SESSION['scloud_intended']);
        // Solo rutas internas del panel.
        if (!is_string($to) || !str_starts_with($to, '/cliente') || str_starts_with($to, '//')) {
            $to = '/cliente';
        }

        return redirect(url($to));
    }

    public function showRegister()
    {
        if (scloud_customer()) {
            return redirect(url('/cliente'));
        }

        return scloud_view('client.register');
    }

    public function register(Request $request)
    {
        if (!$request->input('accept_terms')) {
            return scloud_back_with('/cliente/registro', ['Debes aceptar los términos del servicio.'], $request->all());
        }
        $res = $this->createCustomer($request->all());
        if (!$res['ok']) {
            return scloud_back_with('/cliente/registro', $res['errors'], $request->all());
        }
        scloud_login($res['customer']);
        flash('success', '¡Cuenta creada! Ya puedes contratar tu instancia.');

        return redirect(url('/cliente'));
    }

    public function logout()
    {
        scloud_logout();

        return redirect(url('/'));
    }

    public function showForgot()
    {
        return scloud_view('client.forgot');
    }

    public function forgot(Request $request)
    {
        $email    = strtolower(trim((string) $request->input('email')));
        $customer = Customer::where('email', $email)->where('status', 'active')->first();
        if ($customer) {
            $token = bin2hex(random_bytes(32));
            $customer->reset_token      = hash('sha256', $token);
            $customer->reset_expires_at = now()->addHour();
            $customer->save();
            Mailer::passwordReset($customer, url('/cliente/restablecer/' . $token));
        }
        // Misma respuesta exista o no la cuenta.
        flash('success', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.');

        return redirect(url('/cliente/recuperar'));
    }

    public function showReset(string $token)
    {
        if (!$this->findByToken($token)) {
            flash('error', 'El enlace no es válido o ya venció. Solicita uno nuevo.');

            return redirect(url('/cliente/recuperar'));
        }

        return scloud_view('client.reset', ['token' => $token]);
    }

    public function reset(Request $request, string $token)
    {
        $customer = $this->findByToken($token);
        if (!$customer) {
            flash('error', 'El enlace no es válido o ya venció. Solicita uno nuevo.');

            return redirect(url('/cliente/recuperar'));
        }
        $password = (string) $request->input('password');
        if ($error = $this->passwordError($password, (string) $request->input('password_confirmation'))) {
            return scloud_back_with('/cliente/restablecer/' . $token, [$error]);
        }
        $customer->setPassword($password);
        $customer->reset_token      = null;
        $customer->reset_expires_at = null;
        $customer->save();
        flash('success', 'Contraseña actualizada. Inicia sesión.');

        return redirect(url('/cliente/login'));
    }

    /**
     * Valida credenciales (con límite de intentos por sesión).
     *
     * @return array{ok: bool, customer?: Customer, errors?: string[]}
     */
    public function attempt(string $email, string $password): array
    {
        scloud_session();
        $lock = $_SESSION['scloud_login_lock'] ?? ['n' => 0, 'until' => 0];
        if (($lock['until'] ?? 0) > time()) {
            return ['ok' => false, 'errors' => ['Demasiados intentos. Espera unos minutos e inténtalo de nuevo.']];
        }

        $customer = Customer::where('email', strtolower(trim($email)))->first();
        // Siempre se verifica un hash para no revelar si el correo existe por el tiempo de respuesta.
        $valid = password_verify($password, $customer->password ?? '$2y$10$usesomesillystringforsaltuQ1GQZ8f2v6kZ8x0Z3d2k0m6mQWvO');
        if (!$customer || !$valid) {
            $lock['n'] = ($lock['n'] ?? 0) + 1;
            if ($lock['n'] >= self::MAX_ATTEMPTS) {
                $lock = ['n' => 0, 'until' => time() + self::LOCK_SECONDS];
            }
            $_SESSION['scloud_login_lock'] = $lock;

            return ['ok' => false, 'errors' => ['Correo o contraseña incorrectos.']];
        }
        if ($customer->status !== 'active') {
            return ['ok' => false, 'errors' => ['Tu cuenta está deshabilitada. Contáctanos para más información.']];
        }
        unset($_SESSION['scloud_login_lock']);

        return ['ok' => true, 'customer' => $customer];
    }

    /**
     * Registra un cliente nuevo (registro y checkout).
     *
     * @return array{ok: bool, customer?: Customer, errors?: string[]}
     */
    public function createCustomer(array $in): array
    {
        $errors = [];
        $name   = trim((string) ($in['name'] ?? ''));
        $email  = strtolower(trim((string) ($in['email'] ?? '')));
        if (mb_strlen($name) < 3) {
            $errors[] = 'Escribe tu nombre completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Escribe un correo válido.';
        } elseif (Customer::where('email', $email)->exists()) {
            $errors[] = 'Ya existe una cuenta con ese correo. Inicia sesión.';
        }
        if ($error = $this->passwordError((string) ($in['password'] ?? ''), (string) ($in['password_confirmation'] ?? ''))) {
            $errors[] = $error;
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $customer = new Customer([
            'name'    => mb_substr($name, 0, 150),
            'company' => $this->clean($in['company'] ?? null, 150),
            'email'   => $email,
            'phone'   => $this->clean($in['phone'] ?? null, 50),
            'tax_id'  => $this->clean($in['tax_id'] ?? null, 40),
            'address' => $this->clean($in['address'] ?? null, 500),
            'city'    => $this->clean($in['city'] ?? null, 100),
            'country' => $this->clean($in['country'] ?? null, 100) ?: 'República Dominicana',
            'status'  => 'active',
        ]);
        $customer->setPassword((string) $in['password']);
        $customer->save();
        Mailer::welcome($customer);
        do_action('scloud_customer_registered', ['customer_id' => $customer->id]);

        return ['ok' => true, 'customer' => $customer];
    }

    public function passwordError(string $password, string $confirmation): ?string
    {
        if (strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($password !== $confirmation) {
            return 'Las contraseñas no coinciden.';
        }

        return null;
    }

    private function findByToken(string $token): ?Customer
    {
        return Customer::where('reset_token', hash('sha256', $token))->where('reset_expires_at', '>', now())->where('status', 'active')->first();
    }

    private function clean(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
