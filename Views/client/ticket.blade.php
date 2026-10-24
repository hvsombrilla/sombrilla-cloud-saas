@extends('scloud::client.layout')
@section('title', '[' . $ticket->code . '] ' . $ticket->subject)

@section('client')
<div class="sc-page-head"><p><a href="{{ url('/cliente/tickets') }}">← Soporte</a></p>
  <div class="sc-card-head"><h1 style="font-size:1.4rem">{{ $ticket->subject }}</h1>{!! scloud_chip($ticket->statusLabel(), $ticket->statusTone()) !!}</div>
  <p class="sc-muted">{{ $ticket->code }} · {{ $ticket->departmentLabel() }} · Prioridad {{ strtolower($ticket->priorityLabel()) }}@if($ticket->service) · {{ $ticket->service->title() }}@endif</p></div>

@foreach($replies as $r)
  <div class="sc-msg {{ $r->isStaff() ? 'is-staff' : '' }}">
    <header><strong>{{ $r->isStaff() ? ($r->author_name ?: 'Soporte') . ' (equipo)' : 'Tú' }}</strong><span>{{ $r->created_at->format('d/m/Y H:i') }}</span></header>
    <div class="sc-msg-body">{{ $r->message }}</div>
    @if($r->attachment)<p style="margin:10px 0 0"><a href="{{ url('/cliente/tickets/adjunto/' . $r->id) }}" target="_blank">📎 {{ $r->attachment_name ?: 'Adjunto' }}</a></p>@endif
  </div>
@endforeach

@if($ticket->status !== 'closed')
<div class="sc-card">
  <h3>Responder</h3>
  <form method="post" action="{{ url('/cliente/tickets/' . $ticket->id . '/responder') }}" enctype="multipart/form-data">
    {!! scloud_csrf() !!}
    <div class="sc-field"><textarea name="message" required placeholder="Escribe tu respuesta…"></textarea></div>
    <div class="sc-field"><input type="file" name="attachment"></div>
    <button class="sc-btn">Enviar respuesta</button>
  </form>
  <form method="post" action="{{ url('/cliente/tickets/' . $ticket->id . '/cerrar') }}" style="margin-top:12px" onsubmit="return confirm('¿Cerrar este ticket?')">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ghost sc-btn--sm">Cerrar ticket</button></form>
</div>
@else
<div class="sc-alert sc-alert--info">Este ticket está cerrado. Si necesitas más ayuda, <a href="{{ url('/cliente/tickets/nuevo') }}">abre uno nuevo</a>.</div>
@endif
@endsection
