@extends('scloud::site.layout')
@section('title', 'Ingresar — ' . scloud_setting('brand_name'))
@section('content')
<div class="sc-auth"><div class="sc-card">
  <h2>Ingresar a tu cuenta</h2>
  <form method="post" action="{{ url('/cliente/login') }}">
    {!! scloud_csrf() !!}
    <div class="sc-field"><label>Correo electrónico</label><input type="email" name="email" value="{{ scloud_old('email') }}" required autofocus autocomplete="username"></div>
    <div class="sc-field"><label>Contraseña</label><input type="password" name="password" required autocomplete="current-password"></div>
    <button class="sc-btn sc-btn--block" type="submit">Ingresar</button>
  </form>
  <p class="sc-center" style="margin:18px 0 0"><a href="{{ url('/cliente/recuperar') }}">¿Olvidaste tu contraseña?</a><br><span class="sc-muted">¿Eres nuevo?</span> <a href="{{ url('/cliente/registro') }}">Crea tu cuenta</a></p>
</div></div>
@endsection
