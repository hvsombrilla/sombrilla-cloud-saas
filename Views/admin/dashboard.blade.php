@extends('scloud::admin.layout')
@section('title', 'Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>☂ Sombrilla Cloud</h1><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/') }}" target="_blank">Ver sitio público ↗</a></div>

@if($stats['failed'])<div class="sc-alert sc-alert--bad">{{ $stats['failed'] }} servicio(s) no se pudieron crear. <a href="{{ url('/scloud/servicios?estado=failed') }}">Revisar</a></div>@endif

<div class="sc-stats">
  <a class="sc-stat" href="{{ url('/scloud/pagos') }}"><span>Comprobantes por validar</span><strong>{{ $stats['proofs'] }}</strong></a>
  <a class="sc-stat" href="{{ url('/scloud/pedidos?estado=pending') }}"><span>Pedidos pendientes de pago</span><strong>{{ $stats['pending_orders'] }}</strong></a>
  <a class="sc-stat" href="{{ url('/scloud/servicios?estado=active') }}"><span>Servicios activos</span><strong>{{ $stats['active'] }}</strong></a>
  <a class="sc-stat" href="{{ url('/scloud/servicios?estado=suspended') }}"><span>Suspendidos</span><strong>{{ $stats['suspended'] }}</strong></a>
  <a class="sc-stat" href="{{ url('/scloud/tickets') }}"><span>Tickets por atender</span><strong>{{ $stats['tickets'] }}</strong></a>
  <a class="sc-stat" href="{{ url('/scloud/clientes') }}"><span>Clientes</span><strong>{{ $stats['customers'] }}</strong></a>
  <div class="sc-stat"><span>Ingreso mensual recurrente</span><strong>{{ scloud_money($stats['mrr']) }}</strong></div>
</div>

<div class="sc-cols">
  <div class="sc-card">
    <div class="sc-card-head"><h3>Pedidos recientes</h3><a href="{{ url('/scloud/pedidos') }}">Ver todos</a></div>
    <div class="sc-table-wrap"><table class="sc-table"><tbody>
      @forelse($recentOrders as $o)<tr><td><a href="{{ url('/scloud/pedidos/' . $o->id) }}"><strong>{{ $o->code }}</strong></a></td><td>{{ $o->customer->name ?? '—' }}</td><td>{{ scloud_money($o->total) }}</td><td>{!! scloud_chip($o->statusLabel(), $o->statusTone()) !!}</td></tr>
      @empty<tr><td class="sc-empty">Sin pedidos todavía.</td></tr>@endforelse
    </tbody></table></div>
  </div>
  <div class="sc-card">
    <div class="sc-card-head"><h3>Tickets por atender</h3><a href="{{ url('/scloud/tickets') }}">Ver todos</a></div>
    <div class="sc-table-wrap"><table class="sc-table"><tbody>
      @forelse($openTickets as $t)<tr><td><a href="{{ url('/scloud/tickets/' . $t->id) }}">[{{ $t->code }}] {{ \Illuminate\Support\Str::limit($t->subject, 40) }}</a></td><td>{{ $t->customer->name ?? '—' }}</td><td>{!! scloud_chip($t->statusLabel(), $t->statusTone()) !!}</td></tr>
      @empty<tr><td class="sc-empty">Nada pendiente 🎉</td></tr>@endforelse
    </tbody></table></div>
  </div>
</div>

<div class="sc-cols">
  <div class="sc-card">
    <div class="sc-card-head"><h3>Renovaciones próximas (14 días)</h3></div>
    <div class="sc-table-wrap"><table class="sc-table"><tbody>
      @forelse($renewals as $s)<tr><td><a href="{{ url('/scloud/servicios/' . $s->id) }}">{{ $s->title() }}</a></td><td>{{ $s->customer->name ?? '—' }}</td><td>{{ scloud_date($s->next_due_date) }}</td><td class="sc-right">{{ scloud_money($s->amount) }}</td></tr>
      @empty<tr><td class="sc-empty">Sin renovaciones próximas.</td></tr>@endforelse
    </tbody></table></div>
  </div>
  <div class="sc-card">
    <div class="sc-card-head"><h3>Mantenimiento</h3><span class="sc-muted">Último cron: {{ $cronLast ?: 'nunca' }}</span></div>
    @forelse($lastErrors as $l)<div class="sc-line"><span><span class="sc-chip sc-chip--bad">{{ $l->action }}</span> {{ \Illuminate\Support\Str::limit($l->message, 90) }}</span><span class="sc-muted">{{ $l->created_at->format('d/m H:i') }}</span></div>
    @empty<div class="sc-muted">Sin errores recientes al crear instancias ni en la facturación.</div>@endforelse
    @if($failedServices->count())<hr style="border:0;border-top:1px solid var(--border)"><strong>Servicios con error</strong>@foreach($failedServices as $s)<div class="sc-line"><a href="{{ url('/scloud/servicios/' . $s->id) }}">{{ $s->title() }}</a><span class="sc-muted">{{ \Illuminate\Support\Str::limit($s->last_error, 60) }}</span></div>@endforeach @endif
  </div>
</div>
@endsection
