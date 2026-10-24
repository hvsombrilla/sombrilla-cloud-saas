@extends('scloud::admin.layout')
@section('title', 'Tickets — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Tickets de soporte</h1></div>
<form class="sc-filters" method="get">
  <input type="text" name="q" value="{{ $q }}" placeholder="Código o asunto…">
  <select name="estado" onchange="this.form.submit()"><option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Por atender</option>@foreach(\SombrillaCloud\Models\Ticket::STATUSES as $k => [$l])<option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach<option value="all" {{ $status === 'all' ? 'selected' : '' }}>Todos</option></select>
  <button class="sc-btn sc-btn--ghost">Buscar</button>
</form>
<div class="sc-card"><div class="sc-table-wrap"><table class="sc-table">
  <thead><tr><th>Ticket</th><th>Asunto</th><th>Cliente</th><th>Depto.</th><th>Prioridad</th><th>Estado</th><th>Actividad</th></tr></thead>
  <tbody>
    @forelse($tickets as $t)<tr><td class="sc-mono"><a href="{{ url('/scloud/tickets/' . $t->id) }}">{{ $t->code }}</a></td><td><a href="{{ url('/scloud/tickets/' . $t->id) }}">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</a></td><td>{{ $t->customer->name ?? '—' }}</td><td>{{ $t->departmentLabel() }}</td><td>{!! scloud_chip($t->priorityLabel(), $t->priorityTone()) !!}</td><td>{!! scloud_chip($t->statusLabel(), $t->statusTone()) !!}</td><td>{{ $t->last_reply_at ? $t->last_reply_at->format('d/m H:i') : '—' }}</td></tr>
    @empty<tr><td colspan="7" class="sc-empty">No hay tickets.</td></tr>@endforelse
  </tbody>
</table></div>{!! scloud_pager($tickets) !!}</div>
@endsection
