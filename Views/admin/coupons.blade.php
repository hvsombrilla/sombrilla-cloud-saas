@extends('scloud::admin.layout')
@section('title', 'Cupones — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Cupones de descuento</h1><a class="sc-btn" href="{{ url('/scloud/cupones/nuevo') }}">+ Nuevo cupón</a></div>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Código</th><th>Descuento</th><th>Aplica a</th><th>Usos</th><th>Vigencia</th><th>Estado</th></tr></thead>
  <tbody>
    @forelse($coupons as $c)<tr>
      <td><a href="{{ url('/scloud/cupones/' . $c->id) }}"><strong class="sc-mono">{{ $c->code }}</strong></a>@if($c->description)<div class="sc-muted">{{ $c->description }}</div>@endif</td>
      <td>{{ $c->valueLabel() }}</td>
      <td>{{ $c->plan->name ?? 'Todos los planes' }}</td>
      <td>{{ $uses[$c->id] }}@if($c->max_uses) / {{ $c->max_uses }}@endif</td>
      <td>@if($c->starts_at || $c->expires_at){{ $c->starts_at ? scloud_date($c->starts_at) : '…' }} → {{ $c->expires_at ? scloud_date($c->expires_at) : '…' }}@else Sin límite @endif</td>
      <td>{!! scloud_chip($c->is_active ? 'Activo' : 'Inactivo', $c->is_active ? 'ok' : '') !!}</td></tr>
    @empty<tr><td colspan="6" class="sc-empty">Aún no hay cupones. <a href="{{ url('/scloud/cupones/nuevo') }}">Crea el primero</a>.</td></tr>@endforelse
  </tbody>
</table></div></div>
<p class="sc-muted" style="font-size:.88rem">El descuento se aplica a la primera factura del pedido (plan e instalación, no al dominio). Las renovaciones se cobran al precio normal del plan.</p>
@endsection
