@extends('scloud::site.layout')
@section('title', 'Página no encontrada — ' . scloud_setting('brand_name'))
@section('content')
<div class="sc-page"><div class="sc-wrap sc-center" style="max-width:520px">
  <h1>Página no encontrada</h1>
  <p class="sc-muted">Lo que buscas no existe o fue movido.</p>
  <a class="sc-btn" href="{{ url('/') }}">Volver al inicio</a>
</div></div>
@endsection
