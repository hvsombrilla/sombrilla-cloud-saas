@extends('scloud::site.layout')
@section('title', 'Recuperar contraseña — ' . scloud_setting('brand_name'))
@section('content')
<div class="sc-auth"><div class="sc-card">
  <h2>Recuperar contraseña</h2>
  <p class="sc-muted">Escribe tu correo y te enviaremos un enlace para crear una contraseña nueva.</p>
  <form method="post" action="{{ url('/cliente/recuperar') }}">
    {!! scloud_csrf() !!}
    <div class="sc-field"><label>Correo electrónico</label><input type="email" name="email" required autofocus></div>
    <button class="sc-btn sc-btn--block" type="submit">Enviar enlace</button>
  </form>
  <p class="sc-center" style="margin:18px 0 0"><a href="{{ url('/cliente/login') }}">← Volver a ingresar</a></p>
</div></div>
@endsection
