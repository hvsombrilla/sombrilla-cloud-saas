@extends('scloud::admin.layout')
@section('title', 'Clientes — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Clientes</h1></div>
<form class="sc-filters" method="get"><input type="text" name="q" value="{{ $q }}" placeholder="Nombre, correo, empresa o RNC…"><button class="sc-btn sc-btn--ghost">Buscar</button></form>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Cliente</th><th>Contacto</th><th>Servicios</th><th>Tickets</th><th>Registro</th><th>Estado</th></tr></thead>
  <tbody>
    @forelse($customers as $c)<tr><td><a href="{{ url('/scloud/clientes/' . $c->id) }}"><strong>{{ $c->name }}</strong></a><div class="sc-muted">{{ $c->company }}</div></td><td>{{ $c->email }}<div class="sc-muted">{{ $c->phone }}</div></td><td>{{ $c->services_count }}</td><td>{{ $c->tickets_count }}</td><td>{{ scloud_date($c->created_at) }}</td><td>{!! scloud_chip($c->status === 'active' ? 'Activo' : 'Deshabilitado', $c->status === 'active' ? 'ok' : 'bad') !!}</td></tr>
    @empty<tr><td colspan="6" class="sc-empty">No hay clientes.</td></tr>@endforelse
  </tbody>
</table></div>{!! scloud_pager($customers) !!}</div>
@endsection
