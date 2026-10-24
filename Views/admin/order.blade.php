@extends('scloud::admin.layout')
@section('title', 'Pedido ' . $order->code)

@section('scloud')
<div class="sca-head"><h1>Pedido {{ $order->code }} {!! scloud_chip($order->statusLabel(), $order->statusTone()) !!}</h1>
  <div class="sc-actions">
    @if(in_array($order->status, ['paid', 'active'], true) && $order->services->whereIn('status', ['pending', 'failed'])->count())
      <form method="post" action="{{ url('/scloud/pedidos/' . $order->id . '/activar') }}">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ok">Crear instancias</button></form>
    @endif
    @if($order->status === 'pending')
      <form method="post" action="{{ url('/scloud/pedidos/' . $order->id . '/cancelar') }}" onsubmit="return confirm('¿Cancelar el pedido y anular su factura?')">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--danger">Cancelar pedido</button></form>
    @endif
  </div></div>

<div class="sc-cols-21">
  <div>
    <div class="sc-card"><h3>Servicios</h3><div class="sc-table-wrap"><table class="sc-table"><thead><tr><th>Plan</th><th>Instancia</th><th>Ciclo</th><th>Importe</th><th>Estado</th></tr></thead><tbody>
      @foreach($order->services as $s)<tr><td><a href="{{ url('/scloud/servicios/' . $s->id) }}">{{ $s->plan->name ?? '—' }}</a></td><td>{{ $s->host() ?: '—' }}@if($s->domain)<div class="sc-muted">{{ $s->domain }}</div>@endif</td><td>{{ $s->cycleLabel() }}</td><td>{{ scloud_money($s->amount) }}</td><td>{!! scloud_chip($s->statusLabel(), $s->statusTone()) !!}@if($s->last_error)<div class="sc-muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($s->last_error, 80) }}</div>@endif</td></tr>@endforeach
    </tbody></table></div></div>
    <div class="sc-card"><h3>Comprobantes</h3>
      @forelse($proofs as $p)<div class="sc-line"><span>{{ scloud_date($p->paid_at) }} · {{ scloud_money($p->amount) }} · <span class="sc-mono">{{ $p->reference }}</span></span>{!! scloud_chip($p->statusLabel(), $p->statusTone()) !!}</div>
      @empty<div class="sc-muted">El cliente aún no ha subido comprobantes.</div>@endforelse
      @if($proofs->where('status', 'pending')->count())<p style="margin:12px 0 0"><a class="sc-btn sc-btn--sm" href="{{ url('/scloud/pagos') }}">Ir a validar pagos</a></p>@endif
    </div>
  </div>
  <div>
    <div class="sc-card"><h3>Cliente</h3><dl class="sc-kv" style="grid-template-columns:90px 1fr"><dt>Nombre</dt><dd><a href="{{ url('/scloud/clientes/' . $order->customer_id) }}">{{ $order->customer->name ?? '—' }}</a></dd><dt>Correo</dt><dd>{{ $order->customer->email ?? '' }}</dd><dt>IP</dt><dd class="sc-mono">{{ $order->ip ?: '—' }}</dd></dl></div>
    <div class="sc-card"><h3>Factura (ERP)</h3>
      @if($invoice)<dl class="sc-kv" style="grid-template-columns:90px 1fr"><dt>Número</dt><dd><a href="{{ url('/erp/invoices/' . $invoice->id) }}">{{ $invoice->code }}</a></dd><dt>Estado</dt><dd>{{ $invoice->statusLabel() }}</dd><dt>Total</dt><dd>{{ scloud_money($invoice->total) }}</dd><dt>Saldo</dt><dd>{{ scloud_money($invoice->balance) }}</dd><dt>Vence</dt><dd>{{ scloud_date($invoice->due_date) }}</dd></dl>@else<div class="sc-muted">Sin factura.</div>@endif
    </div>
    @if($order->notes)<div class="sc-card"><h3>Notas del cliente</h3><div style="white-space:pre-wrap">{{ $order->notes }}</div></div>@endif
  </div>
</div>
@endsection
