@extends('scloud::admin.layout')
@section('title', $coupon->exists ? 'Cupón ' . $coupon->code : 'Nuevo cupón')
@php $v = fn($k, $d = null) => scloud_old($k, $coupon->getAttribute($k) ?? $d); $date = fn($k) => scloud_old($k, $coupon->getAttribute($k)?->format('Y-m-d')); @endphp

@section('scloud')
<div class="sca-head"><h1>{{ $coupon->exists ? $coupon->code : 'Nuevo cupón' }}</h1><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/scloud/cupones') }}">← Cupones</a></div>
<form method="post" action="{{ url($coupon->exists ? '/scloud/cupones/' . $coupon->id : '/scloud/cupones') }}">
  {!! scloud_csrf() !!}
  <div class="sc-cols-21">
  <div>
    <div class="sc-card"><h3>Descuento</h3>
      <div class="sc-grid2">
        <div class="sc-field"><label>Código *</label><input type="text" name="code" value="{{ $v('code') }}" required maxlength="40" style="text-transform:uppercase" placeholder="BIENVENIDO20"></div>
        <div class="sc-field"><label>Descripción (interna)</label><input type="text" name="description" value="{{ $v('description') }}"></div>
        <div class="sc-field"><label>Tipo *</label><select name="type">@foreach(\SombrillaCloud\Models\Coupon::TYPES as $k => $label)<option value="{{ $k }}" {{ $v('type') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
        <div class="sc-field"><label>Valor * (% o monto sin impuestos)</label><input type="number" step="0.01" min="0.01" name="value" value="{{ $v('value') }}" required></div>
        <div class="sc-field"><label>Plan</label><select name="plan_id"><option value="">Todos los planes</option>@foreach($plans as $p)<option value="{{ $p->id }}" {{ (int) $v('plan_id') === $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach</select></div>
      </div>
    </div>
    <div class="sc-card"><h3>Límites y vigencia</h3>
      <div class="sc-grid3">
        <div class="sc-field"><label>Máximo de usos</label><input type="number" min="0" name="max_uses" value="{{ $v('max_uses') }}" placeholder="Sin límite"></div>
        <div class="sc-field"><label>Desde</label><input type="date" name="starts_at" value="{{ $date('starts_at') }}"></div>
        <div class="sc-field"><label>Hasta (inclusive)</label><input type="date" name="expires_at" value="{{ $date('expires_at') }}"></div>
      </div>
      <label class="sc-check"><input type="checkbox" name="once_per_customer" value="1" {{ scloud_old('once_per_customer', $coupon->once_per_customer) ? 'checked' : '' }}> Una sola vez por cliente</label>
    </div>
  </div>
  <div>
    <div class="sc-card"><h3>Estado</h3>
      <label class="sc-check"><input type="checkbox" name="is_active" value="1" {{ scloud_old('is_active', $coupon->exists ? $coupon->is_active : true) ? 'checked' : '' }}> Activo (se puede canjear)</label>
      <small class="sc-muted">El descuento se aplica a la primera factura (plan e instalación; el dominio no). Con monto fijo, el descuento nunca supera el importe del pedido.</small>
    </div>
    <div class="sc-actions"><button class="sc-btn">Guardar cupón</button></div>
  </div>
  </div>
</form>
@if($coupon->exists)
<form method="post" action="{{ url('/scloud/cupones/' . $coupon->id . '/eliminar') }}" onsubmit="return confirm('¿Eliminar el cupón? Si ya se usó, solo se desactivará.')" style="margin-top:8px">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--danger sc-btn--sm">Eliminar cupón</button></form>
@endif
@if($coupon->exists)
<div style="margin-top:16px;">
  @include('studio::partials.chatter', ['entity' => 'scloud_coupons', 'id' => $coupon->id])
</div>
@endif
@endsection
