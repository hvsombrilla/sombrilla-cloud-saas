@extends('scloud::admin.layout')
@section('title', 'Planes — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Planes de instancias</h1><a class="sc-btn" href="{{ url('/scloud/planes/nuevo') }}">+ Nuevo plan</a></div>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Plan</th><th>Categoría</th><th>Precios</th><th>Servicios</th><th>Estado</th></tr></thead>
  <tbody>
    @forelse($plans as $p)<tr>
      <td><a href="{{ url('/scloud/planes/' . $p->id) }}"><strong>{{ $p->name }}</strong></a>@if($p->is_featured) ⭐@endif<div class="sc-muted sc-mono">/{{ $p->slug }}</div></td>
      <td>{{ $p->category }}</td>
      <td>@foreach($p->cycles() as $c => $price)<div>{{ \SombrillaCloud\Support\Cycles::label($c) }}: {{ scloud_money($price) }}</div>@endforeach</td>
      <td>{{ $counts[$p->id] ?? 0 }}</td>
      <td>{!! scloud_chip($p->is_active ? 'Activo' : 'Inactivo', $p->is_active ? 'ok' : '') !!}@if($p->is_hidden) {!! scloud_chip('Oculto') !!}@endif</td></tr>
    @empty<tr><td colspan="6" class="sc-empty">Aún no hay planes. <a href="{{ url('/scloud/planes/nuevo') }}">Crea el primero</a>.</td></tr>@endforelse
  </tbody>
</table></div></div>
@endsection
