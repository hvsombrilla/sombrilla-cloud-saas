@extends('scloud::admin.layout')
@section('title', 'Pedidos — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Pedidos</h1></div>
<form class="sc-filters" method="get">
  <input type="text" name="q" value="{{ $q }}" placeholder="Código, cliente o correo…">
  <select name="estado" onchange="this.form.submit()"><option value="">Todos los estados</option>@foreach(\SombrillaCloud\Models\Order::STATUSES as $k => [$l])<option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select>
  <button class="sc-btn sc-btn--ghost">Buscar</button>
</form>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Pedido</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th></tr></thead>
  <tbody>
    @forelse($orders as $o)<tr><td><a href="{{ url('/scloud/pedidos/' . $o->id) }}"><strong>{{ $o->code }}</strong></a></td><td>{{ $o->customer->name ?? '—' }}<div class="sc-muted">{{ $o->customer->email ?? '' }}</div></td><td>{{ scloud_date($o->created_at) }}</td><td>{{ scloud_money($o->total) }}</td><td>{!! scloud_chip($o->statusLabel(), $o->statusTone()) !!}</td></tr>
    @empty<tr><td colspan="5" class="sc-empty">No hay pedidos.</td></tr>@endforelse
  </tbody>
</table></div>{!! scloud_pager($orders) !!}</div>
@endsection
