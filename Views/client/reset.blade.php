@extends('scloud::site.layout')
@section('title', 'Nueva contraseña — ' . scloud_setting('brand_name'))
@section('content')
<div class="sc-auth"><div class="sc-card">
  <h2>Crea una contraseña nueva</h2>
  <form method="post" action="{{ url('/cliente/restablecer/' . $token) }}">
    {!! scloud_csrf() !!}
    <div class="sc-field"><label>Contraseña nueva (mín. 8)</label><input type="password" name="password" required autofocus autocomplete="new-password"></div>
    <div class="sc-field"><label>Repite la contraseña</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
    <button class="sc-btn sc-btn--block" type="submit">Guardar contraseña</button>
  </form>
</div></div>
@endsection
