@extends('scloud::client.layout')
@section('title', 'Mi panel — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><h1 style="font-size:1.8rem">Hola, {{ $customer->firstName() }} 👋</h1><p>Aquí tienes un resumen de tu cuenta.</p></div>

<div class="sc-stats">
  <div class="sc-stat"><span>Servicios activos</span><strong>{{ $services->where('status', 'active')->count() }}</strong></div>
  <div class="sc-stat"><span>Facturas pendientes</span><strong>{{ $unpaid->count() }}</strong></div>
  <div class="sc-stat"><span>Saldo por pagar</span><strong>{{ scloud_money($unpaid->sum('balance')) }}</strong></div>
  <div class="sc-stat"><span>Tickets abiertos</span><strong>{{ $tickets->count() }}</strong></div>
</div>

@if($unpaid->count())
<div class="sc-card">
  <div class="sc-card-head"><h3>Facturas por pagar</h3><a href="{{ url('/cliente/facturas') }}">Ver todas</a></div>
  <div class="sc-table-wrap"><table class="sc-table"><tbody>
    @foreach($unpaid->take(4) as $inv) @php [$label, $tone] = scloud_invoice_status($inv); @endphp
      <tr><td><a href="{{ url('/cliente/facturas/' . $inv->id) }}"><strong>{{ $inv->code }}</strong></a></td><td>Vence {{ scloud_date($inv->due_date) }}</td><td>{!! scloud_chip($label, $tone) !!}</td><td class="sc-right"><strong>{{ scloud_money($inv->balance) }}</strong></td><td class="sc-right"><a class="sc-btn sc-btn--sm" href="{{ url('/cliente/facturas/' . $inv->id) }}">Pagar</a></td></tr>
    @endforeach
  </tbody></table></div>
</div>
@endif

<div class="sc-card">
  <div class="sc-card-head"><h3>Mis servicios</h3><a href="{{ url('/cliente/servicios') }}">Ver todos</a></div>
  @forelse($services as $s)
    <div class="sc-line" style="align-items:center">
      <div><a href="{{ url('/cliente/servicios/' . $s->id) }}"><strong>{{ $s->title() }}</strong></a><div class="sc-muted" style="font-size:.85rem">{{ $s->cycleLabel() }} · {{ $s->next_due_date ? 'Vence ' . scloud_date($s->next_due_date) : 'Sin fecha de vencimiento' }}</div></div>
      <div>{!! scloud_chip($s->statusLabel(), $s->statusTone()) !!}</div>
    </div>
  @empty
    <div class="sc-empty">Aún no tienes servicios. <a href="{{ url('/planes') }}">Contrata tu primer plan</a>.</div>
  @endforelse
</div>

<div class="sc-card">
  <div class="sc-card-head"><h3>Soporte</h3><a class="sc-btn sc-btn--sm" href="{{ url('/cliente/tickets/nuevo') }}">Nuevo ticket</a></div>
  @forelse($tickets as $t)
    <div class="sc-line"><a href="{{ url('/cliente/tickets/' . $t->id) }}">[{{ $t->code }}] {{ $t->subject }}</a>{!! scloud_chip($t->statusLabel(), $t->statusTone()) !!}</div>
  @empty
    <div class="sc-empty" style="padding:12px">No tienes tickets abiertos.</div>
  @endforelse
</div>
@endsection
