<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Coupon;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Services\CouponService;

class CouponController
{
    public function index()
    {
        $coupons = Coupon::with('plan')->orderByDesc('id')->get();
        $uses    = $coupons->mapWithKeys(fn($c) => [$c->id => CouponService::uses($c)]);

        return scloud_view('admin.coupons', ['coupons' => $coupons, 'uses' => $uses]);
    }

    public function create()
    {
        return scloud_view('admin.coupon-form', ['coupon' => new Coupon(['is_active' => true, 'type' => 'percent']), 'plans' => Plan::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        if (is_string($data)) {
            return scloud_back_with('/scloud/cupones/nuevo', [$data], $request->all());
        }
        $coupon = Coupon::create($data);
        flash('success', 'Cupón creado.');

        return redirect(url('/scloud/cupones/' . $coupon->id));
    }

    public function edit(int $id)
    {
        return scloud_view('admin.coupon-form', ['coupon' => Coupon::findOrFail($id), 'plans' => Plan::orderBy('name')->get()]);
    }

    public function update(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data   = $this->validated($request, $coupon);
        if (is_string($data)) {
            return scloud_back_with('/scloud/cupones/' . $id, [$data], $request->all());
        }
        $coupon->update($data);
        flash('success', 'Cupón guardado.');

        return redirect(url('/scloud/cupones/' . $id));
    }

    public function destroy(int $id)
    {
        $coupon = Coupon::findOrFail($id);
        if (\Illuminate\Database\Capsule\Manager::table('scloud_coupon_redemptions')->where('coupon_id', $id)->exists()) {
            $coupon->update(['is_active' => false]);
            flash('success', 'El cupón ya se usó en pedidos: se desactivó en lugar de eliminarse.');
        } else {
            $coupon->delete();
            flash('success', 'Cupón eliminado.');
        }

        return redirect(url('/scloud/cupones'));
    }

    /** @return array|string datos listos para guardar, o el mensaje de error */
    private function validated(Request $request, ?Coupon $coupon): array|string
    {
        $code = CouponService::normalize((string) $request->input('code'));
        if (strlen($code) < 3 || strlen($code) > 40) {
            return 'El código es obligatorio (3-40 caracteres: letras, números, guion).';
        }
        if (Coupon::where('code', $code)->when($coupon, fn($q) => $q->where('id', '!=', $coupon->id))->exists()) {
            return 'Ya existe un cupón con ese código.';
        }

        $type  = (string) $request->input('type');
        $value = round((float) str_replace(',', '', (string) $request->input('value')), 2);
        if (!isset(Coupon::TYPES[$type])) {
            return 'Tipo de descuento no válido.';
        }
        if ($value <= 0 || ($type === 'percent' && $value > 100)) {
            return $type === 'percent' ? 'El porcentaje debe estar entre 0 y 100.' : 'El monto del descuento debe ser mayor que 0.';
        }

        $planId = (int) $request->input('plan_id') ?: null;
        if ($planId && !Plan::find($planId)) {
            return 'El plan seleccionado no existe.';
        }

        $dates = [];
        foreach (['starts_at', 'expires_at'] as $field) {
            $raw = trim((string) $request->input($field));
            if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                return 'Las fechas de vigencia no son válidas.';
            }
            $dates[$field] = $raw ?: null;
        }
        if ($dates['starts_at'] && $dates['expires_at'] && $dates['expires_at'] < $dates['starts_at']) {
            return 'La fecha de vencimiento no puede ser anterior al inicio.';
        }

        return $dates + [
            'code'              => $code,
            'description'       => mb_substr(trim((string) $request->input('description')), 0, 250) ?: null,
            'type'              => $type,
            'value'             => $value,
            'plan_id'           => $planId,
            'max_uses'          => max(0, (int) $request->input('max_uses')) ?: null,
            'once_per_customer' => (bool) $request->input('once_per_customer'),
            'is_active'         => (bool) $request->input('is_active'),
        ];
    }
}
