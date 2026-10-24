@extends('scloud::client.layout')
@section('title', 'Mis servicios — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head sc-card-head"><h1 style="font-size:1.6rem">Mis servicios</h1><a class="sc-btn sc-btn--sm" href="{{ url('/planes') }}">Contratar otro plan</a></div>
<div class="sc-card"><div class="sc-table-wrap">
  <table class="sc-table">
    <thead><tr><th>Servicio</th><th>Ciclo</th><th>Importe</th><th>Vencimiento</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      @forelse($services as $s)
        <tr>
          <td><strong>{{ $s->plan->name ?? 'Plan' }}</strong><div class="sc-muted">{{ $s->domain ?: '—' }}</div></td>
          <td>{{ $s->cycleLabel() }}</td><td>{{ scloud_money($s->amount) }}</td><td>{{ scloud_date($s->next_due_date) }}</td>
          <td>{!! scloud_chip($s->statusLabel(), $s->statusTone()) !!}@if($s->cancel_requested) {!! scloud_chip('Cancelación solicitada', 'warn') !!}@endif</td>
          <td class="sc-right"><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/cliente/servicios/' . $s->id) }}">Administrar</a></td>
        </tr>
      @empty
        <tr><td colspan="6" class="sc-empty">Aún no tienes servicios. <a href="{{ url('/planes') }}">Ver planes</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div></div>
@endsection
