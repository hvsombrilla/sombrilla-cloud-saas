@extends('scloud::admin.layout')
@section('title', '[' . $ticket->code . '] ' . $ticket->subject)

@section('scloud')
<div class="sca-head"><h1>{{ $ticket->subject }} {!! scloud_chip($ticket->statusLabel(), $ticket->statusTone()) !!}</h1><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/scloud/tickets') }}">← Tickets</a></div>
<div class="sc-cols-21">
<div>
  @foreach($replies as $r)
    <div class="sc-msg {{ $r->is_internal ? 'is-internal' : ($r->isStaff() ? 'is-staff' : '') }}">
      <header><strong>{{ $r->author_name ?: ($r->isStaff() ? 'Soporte' : 'Cliente') }} {{ $r->is_internal ? '· nota interna' : ($r->isStaff() ? '· equipo' : '· cliente') }}</strong><span>{{ $r->created_at->format('d/m/Y H:i') }}</span></header>
      <div class="sc-msg-body">{{ $r->message }}</div>
      @if($r->attachment)<p style="margin:8px 0 0"><a href="{{ url('/scloud/tickets/adjunto/' . $r->id) }}" target="_blank">📎 {{ $r->attachment_name ?: 'Adjunto' }}</a></p>@endif
    </div>
  @endforeach
  <div class="sc-card"><h3>Responder</h3>
    <form method="post" action="{{ url('/scloud/tickets/' . $ticket->id . '/responder') }}" enctype="multipart/form-data">
      {!! scloud_csrf() !!}
      <div class="sc-field"><textarea name="message" required placeholder="Escribe la respuesta…"></textarea></div>
      <div class="sc-grid3">
        <div class="sc-field"><label>Estado tras responder</label><select name="status"><option value="answered">Respondido</option><option value="on_hold">En espera</option><option value="closed">Cerrado</option></select></div>
        <div class="sc-field"><label>Adjunto</label><input type="file" name="attachment"></div>
        <div class="sc-field"><label>&nbsp;</label><label class="sc-check"><input type="checkbox" name="internal" value="1"> Nota interna (el cliente no la ve)</label></div>
      </div>
      <button class="sc-btn">Enviar</button>
    </form>
  </div>
</div>
<div>
  <div class="sc-card"><h3>Detalles</h3><dl class="sc-kv" style="grid-template-columns:100px 1fr">
    <dt>Código</dt><dd class="sc-mono">{{ $ticket->code }}</dd><dt>Cliente</dt><dd><a href="{{ url('/scloud/clientes/' . $ticket->customer_id) }}">{{ $ticket->customer->name ?? '—' }}</a><br><span class="sc-muted">{{ $ticket->customer->email ?? '' }}</span></dd>
    <dt>Departamento</dt><dd>{{ $ticket->departmentLabel() }}</dd><dt>Servicio</dt><dd>@if($ticket->service)<a href="{{ url('/scloud/servicios/' . $ticket->service_id) }}">{{ $ticket->service->title() }}</a>@else — @endif</dd><dt>Creado</dt><dd>{{ $ticket->created_at->format('d/m/Y H:i') }}</dd></dl></div>
  <div class="sc-card"><h3>Estado y prioridad</h3>
    <form method="post" action="{{ url('/scloud/tickets/' . $ticket->id . '/estado') }}">
      {!! scloud_csrf() !!}
      <div class="sc-field"><label>Estado</label><select name="status">@foreach(\SombrillaCloud\Models\Ticket::STATUSES as $k => [$l])<option value="{{ $k }}" {{ $ticket->status === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
      <div class="sc-field"><label>Prioridad</label><select name="priority">@foreach(\SombrillaCloud\Models\Ticket::PRIORITIES as $k => [$l])<option value="{{ $k }}" {{ $ticket->priority === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
      <button class="sc-btn sc-btn--ghost">Actualizar</button>
    </form>
  </div>
</div>
</div>
@endsection
