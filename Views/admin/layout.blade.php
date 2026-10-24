@extends('studio::layout')

@push('styles')
<link rel="stylesheet" href="{{ scloud_asset('admin.css') }}">
@endpush

@php
  $__path = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  $__proofs = \SombrillaCloud\Models\PaymentProof::where('status', 'pending')->count();
  $__tabs = [
    ['/scloud', 'Panel', null, fn($p) => $p === '/scloud'],
    ['/scloud/pedidos', 'Pedidos', null, fn($p) => str_starts_with($p, '/scloud/pedidos')],
    ['/scloud/pagos', 'Pagos', $__proofs, fn($p) => str_starts_with($p, '/scloud/pagos')],
    ['/scloud/servicios', 'Servicios', null, fn($p) => str_starts_with($p, '/scloud/servicios')],
    ['/scloud/clientes', 'Clientes', null, fn($p) => str_starts_with($p, '/scloud/clientes')],
    ['/scloud/tickets', 'Tickets', null, fn($p) => str_starts_with($p, '/scloud/tickets')],
    ['/scloud/planes', 'Planes', null, fn($p) => str_starts_with($p, '/scloud/planes')],
    ['/scloud/cupones', 'Cupones', null, fn($p) => str_starts_with($p, '/scloud/cupones')],
    ['/scloud/ajustes', 'Ajustes', null, fn($p) => str_starts_with($p, '/scloud/ajustes')],
  ];
  $__errs = scloud_errors();
@endphp

@section('content')
<div class="sca">
  <nav class="sca-tabs">
    @foreach($__tabs as [$href, $label, $count, $on])<a href="{{ url($href) }}" class="{{ $on($__path) ? 'is-on' : '' }}">{{ $label }}@if($count)<span class="n">{{ $count }}</span>@endif</a>@endforeach
  </nav>
  @if($__errs)<div class="sc-alert sc-alert--bad">@foreach($__errs as $e)<div>{{ $e }}</div>@endforeach</div>@endif
  @yield('scloud')
</div>
@endsection
