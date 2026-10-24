@extends('scloud::admin.layout')
@section('title', 'Servidores WHM — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Servidores WHM</h1><a class="sc-btn" href="{{ url('/scloud/servidores/nuevo') }}">+ Agregar servidor</a></div>
@if(!$servers->count())<div class="sc-alert sc-alert--info">Agrega al menos un servidor WHM con un API Token para crear cuentas de forma automática.</div>@endif
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Servidor</th><th>Host</th><th>Cuentas</th><th>Token</th><th>Estado</th><th></th></tr></thead>
  <tbody>
    @forelse($servers as $s)<tr>
      <td><a href="{{ url('/scloud/servidores/' . $s->id) }}"><strong>{{ $s->name }}</strong></a></td><td class="sc-mono">{{ $s->hostname }}:{{ $s->port }}</td>
      <td>{{ $s->accountCount() }}{{ $s->max_accounts ? ' / ' . $s->max_accounts : '' }}</td>
      <td>{!! scloud_chip($s->hasToken() ? 'Configurado' : 'Falta', $s->hasToken() ? 'ok' : 'bad') !!}</td>
      <td>{!! scloud_chip($s->is_active ? 'Activo' : 'Inactivo', $s->is_active ? 'ok' : '') !!}</td>
      <td class="sc-right"><form method="post" action="{{ url('/scloud/servidores/' . $s->id . '/probar') }}" class="sc-inline">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ghost sc-btn--sm">Probar conexión</button></form></td></tr>
    @empty<tr><td colspan="6" class="sc-empty">Sin servidores.</td></tr>@endforelse
  </tbody>
</table></div></div>
@endsection
