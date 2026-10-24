@extends('scloud::client.layout')
@section('title', 'Mi perfil — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><h1 style="font-size:1.6rem">Mi perfil</h1></div>
<div class="sc-card">
  <h3>Datos de facturación</h3>
  <form method="post" action="{{ url('/cliente/perfil') }}">
    {!! scloud_csrf() !!}
    <div class="sc-grid2">
      <div class="sc-field"><label>Nombre completo *</label><input type="text" name="name" value="{{ scloud_old('name', $customer->name) }}" required></div>
      <div class="sc-field"><label>Empresa</label><input type="text" name="company" value="{{ scloud_old('company', $customer->company) }}"></div>
      <div class="sc-field"><label>Correo *</label><input type="email" name="email" value="{{ scloud_old('email', $customer->email) }}" required></div>
      <div class="sc-field"><label>Teléfono</label><input type="tel" name="phone" value="{{ scloud_old('phone', $customer->phone) }}"></div>
      <div class="sc-field"><label>RNC / Cédula</label><input type="text" name="tax_id" value="{{ scloud_old('tax_id', $customer->tax_id) }}"></div>
      <div class="sc-field"><label>Ciudad</label><input type="text" name="city" value="{{ scloud_old('city', $customer->city) }}"></div>
      <div class="sc-field"><label>Dirección</label><input type="text" name="address" value="{{ scloud_old('address', $customer->address) }}"></div>
      <div class="sc-field"><label>País</label><input type="text" name="country" value="{{ scloud_old('country', $customer->country) }}"></div>
    </div>
    <button class="sc-btn">Guardar cambios</button>
  </form>
</div>
<div class="sc-card">
  <h3>Cambiar contraseña</h3>
  <form method="post" action="{{ url('/cliente/perfil/password') }}">
    {!! scloud_csrf() !!}
    <div class="sc-field"><label>Contraseña actual</label><input type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="sc-grid2">
      <div class="sc-field"><label>Contraseña nueva (mín. 8)</label><input type="password" name="password" required autocomplete="new-password"></div>
      <div class="sc-field"><label>Repite la contraseña</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <button class="sc-btn sc-btn--ghost">Actualizar contraseña</button>
  </form>
</div>
@endsection
