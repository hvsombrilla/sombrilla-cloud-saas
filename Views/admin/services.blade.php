@extends('scloud::admin.layout')
@section('title', 'Servicios — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Servicios</h1></div>
<form class="sc-filters" method="get">
  <input type="text" name="q" value="{{ $q }}" placeholder="Subdominio, dominio, workspace, cliente…">
  <select name="estado" onchange="this.form.submit()"><option value="">Todos los estados</option>@foreach(\SombrillaCloud\Models\Service::STATUSES as $k => [$l])<option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select>
  <button class="sc-btn sc-btn--ghost">Buscar</button>
</form>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Servicio</th><th>Cliente</th><th>Dominio</th><th>Importe</th><th>Vence</th><th>Estado</th></tr></thead>
  <tbody>
    @forelse($services as $s)<tr>
      <td><a href="{{ url('/scloud/servicios/' . $s->id) }}"><strong>{{ $s->plan->name ?? '—' }}</strong></a><div class="sc-muted">{{ $s->workspace_name }} · {{ $s->host() ?: '—' }}</div></td>
      <td>{{ $s->customer->name ?? '—' }}</td><td>@if($s->domain){{ $s->domain }} {!! scloud_chip($s->domainStatusLabel(), $s->domainStatusTone()) !!}@else <span class="sc-muted">—</span>@endif</td>
      <td>{{ scloud_money($s->amount) }}<div class="sc-muted">{{ $s->cycleLabel() }}</div></td><td>{{ scloud_date($s->next_due_date) }}</td>
      <td>{!! scloud_chip($s->statusLabel(), $s->statusTone()) !!}@if($s->cancel_requested) {!! scloud_chip('Cancelación', 'warn') !!}@endif</td></tr>
    @empty<tr><td colspan="7" class="sc-empty">No hay servicios.</td></tr>@endforelse
  </tbody>
</table></div>{!! scloud_pager($services) !!}</div>
@endsection
