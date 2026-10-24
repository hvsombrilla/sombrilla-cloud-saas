@extends('scloud::site.layout')
@php
  $path = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  $nav = [
    ['/cliente', 'Panel', '🏠', fn($p) => $p === '/cliente'],
    ['/cliente/servicios', 'Mis servicios', '🖥️', fn($p) => str_starts_with($p, '/cliente/servicios')],
    ['/cliente/facturas', 'Facturas y pagos', '🧾', fn($p) => str_starts_with($p, '/cliente/facturas') || str_starts_with($p, '/cliente/pedidos')],
    ['/cliente/tickets', 'Soporte', '🛟', fn($p) => str_starts_with($p, '/cliente/tickets')],
    ['/cliente/perfil', 'Mi perfil', '👤', fn($p) => str_starts_with($p, '/cliente/perfil')],
  ];
@endphp

@section('content')
<div class="sc-wrap">
  <div class="sc-account">
    <aside class="sc-side">
      <div class="sc-who"><span class="sc-avatar">{{ $customer->initials() }}</span><div><strong>{{ $customer->firstName() }}</strong><small>{{ $customer->email }}</small></div></div>
      @foreach($nav as [$href, $label, $ico, $on])<a href="{{ url($href) }}" class="{{ $on($path) ? 'is-on' : '' }}"><span>{{ $ico }}</span> {{ $label }}</a>@endforeach
      <hr>
      <a href="{{ url('/planes') }}"><span>➕</span> Contratar otro plan</a>
      <form method="post" action="{{ url('/cliente/salir') }}">{!! scloud_csrf() !!}<a href="#" onclick="this.closest('form').submit();return false"><span>🚪</span> Cerrar sesión</a></form>
    </aside>
    <section>@yield('client')</section>
  </div>
</div>
@endsection
