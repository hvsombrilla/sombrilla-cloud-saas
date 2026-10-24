@extends('scloud::client.layout')
@section('title', 'Soporte — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head sc-card-head"><h1 style="font-size:1.6rem">Soporte</h1><a class="sc-btn sc-btn--sm" href="{{ url('/cliente/tickets/nuevo') }}">Nuevo ticket</a></div>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Ticket</th><th>Asunto</th><th>Departamento</th><th>Estado</th><th>Última actividad</th></tr></thead>
  <tbody>
    @forelse($tickets as $t)
      <tr><td class="sc-mono"><a href="{{ url('/cliente/tickets/' . $t->id) }}">{{ $t->code }}</a></td><td><a href="{{ url('/cliente/tickets/' . $t->id) }}">{{ $t->subject }}</a></td><td>{{ $t->departmentLabel() }}</td><td>{!! scloud_chip($t->statusLabel(), $t->statusTone()) !!}</td><td>{{ scloud_date($t->last_reply_at) }}</td></tr>
    @empty
      <tr><td colspan="5" class="sc-empty">No has abierto tickets.</td></tr>
    @endforelse
  </tbody>
</table></div></div>
@endsection
