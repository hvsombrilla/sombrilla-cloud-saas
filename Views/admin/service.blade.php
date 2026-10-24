@extends('scloud::admin.layout')
@section('title', $service->title())

@section('scloud')
<div class="sca-head"><h1>{{ $service->title() }} {!! scloud_chip($service->statusLabel(), $service->statusTone()) !!}</h1>
  <a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/scloud/clientes/' . $service->customer_id) }}">Cliente: {{ $service->customer->name ?? '—' }}</a></div>

@if($service->last_error)<div class="sc-alert sc-alert--bad"><strong>Último error:</strong> {{ $service->last_error }}</div>@endif
@if($service->cancel_requested)<div class="sc-alert sc-alert--warn">El cliente solicitó la cancelación. No se generarán más renovaciones.</div>@endif

<div class="sc-cols-21">
<div>
  <div class="sc-card"><h3>Cuenta cPanel</h3>
    <dl class="sc-kv"><dt>Usuario</dt><dd class="sc-mono">{{ $service->username ?: '—' }}</dd><dt>Contraseña</dt><dd class="sc-mono">{{ $service->password ? $service->plainPassword() : '—' }}</dd>
      <dt>Servidor</dt><dd>{{ $service->server->name ?? '—' }}</dd><dt>Paquete WHM</dt><dd>{{ $service->whm_package ?: '—' }}</dd><dt>Pedido</dt><dd>@if($service->order)<a href="{{ url('/scloud/pedidos/' . $service->order_id) }}">{{ $service->order->code }}</a>@else — @endif</dd></dl>
    <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">
    <div class="sc-actions">
      @if(in_array($service->status, ['pending', 'failed'], true))<form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/accion') }}">{!! scloud_csrf() !!}<input type="hidden" name="action" value="create"><button class="sc-btn sc-btn--ok">Crear cuenta en WHM</button></form>@endif
      @if($service->status === 'active')<form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/accion') }}" class="sc-actions">{!! scloud_csrf() !!}<input type="hidden" name="action" value="suspend"><input type="text" name="reason" placeholder="Motivo (opcional)" style="width:200px"><button class="sc-btn sc-btn--danger">Suspender</button></form>@endif
      @if($service->status === 'suspended')<form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/accion') }}">{!! scloud_csrf() !!}<input type="hidden" name="action" value="unsuspend"><button class="sc-btn sc-btn--ok">Reactivar</button></form>@endif
      @if($service->isLive())
        <form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/accion') }}" onsubmit="return confirm('¿Generar una contraseña nueva? El cliente deberá recibirla por ticket o correo.')">{!! scloud_csrf() !!}<input type="hidden" name="action" value="password"><button class="sc-btn sc-btn--ghost">Nueva contraseña</button></form>
        <form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/accion') }}" onsubmit="return confirm('¿ELIMINAR la cuenta del servidor? Se pierden todos sus datos.')">{!! scloud_csrf() !!}<input type="hidden" name="action" value="terminate"><button class="sc-btn sc-btn--danger sc-btn--sm">Terminar cuenta</button></form>
      @endif
    </div>
    @if($service->isLive())
    <form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/accion') }}" class="sc-actions" style="margin-top:12px">{!! scloud_csrf() !!}<input type="hidden" name="action" value="package"><input type="text" name="package" value="{{ $service->whm_package }}" placeholder="Paquete de WHM" style="width:220px"><button class="sc-btn sc-btn--ghost sc-btn--sm">Cambiar paquete</button></form>
    @endif
  </div>

  <div class="sc-card"><h3>Facturación</h3>
    <form method="post" action="{{ url('/scloud/servicios/' . $service->id) }}">
      {!! scloud_csrf() !!}
      <div class="sc-grid3">
        <div class="sc-field"><label>Dominio</label><input type="text" name="domain" value="{{ $service->domain }}"></div>
        <div class="sc-field"><label>Ciclo</label><select name="billing_cycle">@foreach(\SombrillaCloud\Support\Cycles::ALL as $k => [$l])<option value="{{ $k }}" {{ $service->billing_cycle === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
        <div class="sc-field"><label>Importe (sin impuestos)</label><input type="number" step="0.01" name="amount" value="{{ number_format($service->amount, 2, '.', '') }}"></div>
        <div class="sc-field"><label>Próximo vencimiento</label><input type="date" name="next_due_date" value="{{ $service->next_due_date ? $service->next_due_date->format('Y-m-d') : '' }}"></div>
      </div>
      <div class="sc-field"><label>Notas internas</label><textarea name="notes" style="min-height:60px">{{ $service->notes }}</textarea></div>
      <label class="sc-check"><input type="checkbox" name="cancel_requested" value="1" {{ $service->cancel_requested ? 'checked' : '' }}> Cancelación solicitada (no generar renovaciones)</label>
      <div class="sc-actions"><button class="sc-btn">Guardar</button></div>
    </form>
    @if($service->isLive())<form method="post" action="{{ url('/scloud/servicios/' . $service->id . '/factura') }}" style="margin-top:12px" onsubmit="return confirm('¿Generar la factura de renovación del periodo actual?')">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ghost sc-btn--sm">Generar factura de renovación</button></form>@endif
    @if($invoices->count())<hr style="border:0;border-top:1px solid var(--border);margin:14px 0"><strong>Facturas</strong>@foreach($invoices as $l)@if($l->invoice)<div class="sc-line"><a href="{{ url('/erp/invoices/' . $l->invoice_id) }}">{{ $l->invoice->code }}</a><span>{{ $l->kind === 'renewal' ? 'Renovación' : 'Pedido' }} · {{ scloud_money($l->invoice->total) }} · {{ $l->invoice->statusLabel() }}</span></div>@endif @endforeach @endif
  </div>
</div>
<div class="sc-card"><h3>Historial</h3>
  @forelse($service->logs->take(40) as $l)<div class="sc-line" style="flex-direction:column;gap:2px"><span><span class="sc-chip {{ $l->ok ? 'sc-chip--ok' : 'sc-chip--bad' }}">{{ $l->action }}</span> <span class="sc-muted">{{ $l->created_at->format('d/m/Y H:i') }}</span></span><span style="font-size:13px">{{ $l->message }}</span></div>
  @empty<div class="sc-muted">Sin actividad.</div>@endforelse
</div>
</div>
@endsection
