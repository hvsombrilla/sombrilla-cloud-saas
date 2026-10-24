<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Customer;
use SombrillaCloud\Services\BillingService;

class CustomerController
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $customers = Customer::withCount(['services', 'tickets'])
            ->when($q !== '', fn($x) => $x->where(fn($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                ->orWhere('company', 'like', "%{$q}%")->orWhere('tax_id', 'like', "%{$q}%")))
            ->orderByDesc('id')->paginate(25)->appends($request->query());

        return scloud_view('admin.customers', ['customers' => $customers, 'q' => $q]);
    }

    public function show(int $id)
    {
        $customer = Customer::findOrFail($id);

        return scloud_view('admin.customer', [
            'customer' => $customer,
            'services' => $customer->services()->with('plan')->get(),
            'orders'   => $customer->orders()->limit(10)->get(),
            'tickets'  => $customer->tickets()->limit(10)->get(),
            'invoices' => BillingService::invoicesFor($customer)->take(10),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);
        $name     = trim((string) $request->input('name'));
        $email    = strtolower(trim((string) $request->input('email')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || Customer::where('email', $email)->where('id', '!=', $id)->exists()) {
            flash('error', 'Revisa el nombre y el correo (debe ser válido y único).');

            return redirect(url('/scloud/clientes/' . $id));
        }
        $f = fn(string $k, int $max) => ($v = trim((string) $request->input($k))) === '' ? null : mb_substr($v, 0, $max);
        $customer->fill([
            'name' => mb_substr($name, 0, 150), 'email' => $email, 'company' => $f('company', 150), 'phone' => $f('phone', 50),
            'tax_id' => $f('tax_id', 40), 'address' => $f('address', 500), 'city' => $f('city', 100), 'country' => $f('country', 100),
            'status' => $request->input('status') === 'disabled' ? 'disabled' : 'active', 'notes' => $f('notes', 5000),
        ]);
        if ($pass = (string) $request->input('new_password')) {
            if (strlen($pass) < 8) {
                flash('error', 'La contraseña debe tener al menos 8 caracteres.');

                return redirect(url('/scloud/clientes/' . $id));
            }
            $customer->setPassword($pass);
        }
        $customer->save();
        BillingService::syncContact($customer);
        flash('success', 'Cliente actualizado.');

        return redirect(url('/scloud/clientes/' . $id));
    }
}
