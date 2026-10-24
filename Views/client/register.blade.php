@extends('scloud::site.layout')
@section('title', 'Crear cuenta — ' . scloud_setting('brand_name'))
@section('content')
<div class="sc-auth sc-auth--wide"><div class="sc-card">
  <h2>Crea tu cuenta</h2>
  <form method="post" action="{{ url('/cliente/registro') }}">
    {!! scloud_csrf() !!}
    <div class="sc-grid2">
      <div class="sc-field"><label>Nombre completo *</label><input type="text" name="name" value="{{ scloud_old('name') }}" required autocomplete="name"></div>
      <div class="sc-field"><label>Empresa</label><input type="text" name="company" value="{{ scloud_old('company') }}" autocomplete="organization"></div>
      <div class="sc-field"><label>Correo electrónico *</label><input type="email" name="email" value="{{ scloud_old('email') }}" required autocomplete="email"></div>
      <div class="sc-field"><label>Teléfono</label><input type="tel" name="phone" value="{{ scloud_old('phone') }}" autocomplete="tel"></div>
      <div class="sc-field"><label>RNC / Cédula</label><input type="text" name="tax_id" value="{{ scloud_old('tax_id') }}"></div>
      <div class="sc-field"><label>Ciudad</label><input type="text" name="city" value="{{ scloud_old('city') }}"></div>
      <div class="sc-field"><label>Contraseña * (mín. 8)</label><input type="password" name="password" required autocomplete="new-password"></div>
      <div class="sc-field"><label>Repite la contraseña *</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <label class="sc-check" style="margin-bottom:16px"><input type="checkbox" name="accept_terms" value="1" required> <span>Acepto los términos del servicio y la política de privacidad.</span></label>
    <button class="sc-btn sc-btn--block" type="submit">Crear cuenta</button>
  </form>
  <p class="sc-center sc-muted" style="margin:18px 0 0">¿Ya tienes cuenta? <a href="{{ url('/cliente/login') }}">Ingresa</a></p>
</div></div>
@endsection
