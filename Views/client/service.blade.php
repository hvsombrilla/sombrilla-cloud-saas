@extends('scloud::client.layout')
@section('title', $service->title() . ' — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><p><a href="{{ url('/cliente/servicios') }}">← Mis servicios</a></p><h1 style="font-size:1.6rem">{{ $service->title() }} {!! scloud_chip($service->statusLabel(), $service->statusTone()) !!}</h1></div>

@if($service->status === 'suspended')<div class="sc-alert sc-alert--bad">Este servicio está suspendido{{ $service->suspend_reason ? ': ' . $service->suspend_reason : '' }}. @if($renewal && $renewal->invoice)<a href="{{ url('/cliente/facturas/' . $renewal->invoice_id) }}">Paga tu factura {{ $renewal->invoice->code }}</a> para reactivarlo.@endif</div>@endif
@if($service->status === 'pending')<div class="sc-alert sc-alert--info">Tu servicio se activará automáticamente cuando validemos el pago de tu pedido @if($service->order)<a href="{{ url('/cliente/pedidos/' . $service->order->code) }}">{{ $service->order->code }}</a>@endif.</div>@endif
@if($service->status === 'failed')<div class="sc-alert sc-alert--warn">Estamos terminando de configurar tu cuenta. Nuestro equipo ya fue notificado; si pasa mucho tiempo, <a href="{{ url('/cliente/tickets/nuevo?servicio=' . $service->id) }}">abre un ticket</a>.</div>@endif
@if($renewal && $renewal->invoice && in_array($renewal->invoice->status, ['issued', 'partial', 'overdue', 'draft'], true) && $service->status === 'active')<div class="sc-alert sc-alert--warn">Tienes una factura de renovación pendiente: <a href="{{ url('/cliente/facturas/' . $renewal->invoice_id) }}">{{ $renewal->invoice->code }}</a> por {{ scloud_money($renewal->invoice->balance) }}.</div>@endif

<div class="sc-card">
  <div class="sc-card-head"><h3>Detalles</h3>
    @if($service->status === 'active')<form method="post" action="{{ url('/cliente/servicios/' . $service->id . '/cpanel') }}">{!! scloud_csrf() !!}<button class="sc-btn">Entrar a cPanel</button></form>@endif</div>
  <dl class="sc-kv">
    <dt>Plan</dt><dd>{{ $service->plan->name ?? '—' }}</dd>
    <dt>Dominio</dt><dd>{{ $service->domain ?: '—' }}</dd>
    @if($service->username)<dt>Usuario cPanel</dt><dd class="sc-mono">{{ $service->username }}</dd>@endif
    @if($service->server && $service->status !== 'pending')<dt>Servidor</dt><dd>{{ $service->server->name }}@if($usage && !empty($usage['ip'])) · IP {{ $usage['ip'] }}@endif</dd>
      @if($service->server->ns1)<dt>Nameservers</dt><dd class="sc-mono">{{ $service->server->ns1 }}@if($service->server->ns2)<br>{{ $service->server->ns2 }}@endif</dd>@endif
      <dt>URL de cPanel</dt><dd><a href="{{ $service->server->cpanelUrl() }}" target="_blank" rel="noopener">{{ $service->server->cpanelUrl() }}</a></dd>@endif
    <dt>Ciclo</dt><dd>{{ $service->cycleLabel() }} · {{ scloud_money($service->amount) }} <span class="sc-faint">+ impuestos</span></dd>
    <dt>Registrado</dt><dd>{{ scloud_date($service->registration_date) }}</dd>
    <dt>Próximo vencimiento</dt><dd>{{ scloud_date($service->next_due_date) }}</dd>
  </dl>
  @if($usage && !empty($usage['disk_limit']))
    @php $used = (float) preg_replace('/[^0-9.]/', '', (string) $usage['disk_used']); $lim = (float) preg_replace('/[^0-9.]/', '', (string) $usage['disk_limit']); $pct = $lim > 0 ? min(100, round($used / $lim * 100)) : 0; @endphp
    <div style="margin-top:18px"><div class="sc-muted" style="font-size:.85rem;margin-bottom:6px">Disco: {{ $usage['disk_used'] }} de {{ $usage['disk_limit'] }}</div><div class="sc-progress"><i style="width:{{ $pct }}%"></i></div></div>
  @endif
</div>

@if($service->status === 'active')
<div class="sc-card">
  <h3>Cambiar contraseña de cPanel</h3>
  <form method="post" action="{{ url('/cliente/servicios/' . $service->id . '/password') }}">
    {!! scloud_csrf() !!}
    <div class="sc-grid2">
      <div class="sc-field"><label>Contraseña nueva</label><input type="password" name="password" required minlength="12" autocomplete="new-password"><small>Mínimo 12 caracteres con mayúsculas, minúsculas y números.</small></div>
      <div class="sc-field"><label>Repite la contraseña</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <button class="sc-btn sc-btn--ghost">Actualizar contraseña</button>
  </form>
</div>
@endif

@if($service->isLive())
<div class="sc-card">
  <h3>Soporte y cancelación</h3>
  <p class="sc-muted">¿Algo no funciona? <a href="{{ url('/cliente/tickets/nuevo?servicio=' . $service->id) }}">Abre un ticket</a>.</p>
  @if($service->cancel_requested)
    <div class="sc-alert sc-alert--warn" style="margin:0">Solicitaste la cancelación. El servicio seguirá activo hasta su vencimiento y no se generarán más renovaciones.</div>
  @else
    <form method="post" action="{{ url('/cliente/servicios/' . $service->id . '/cancelar') }}" onsubmit="return confirm('¿Solicitar la cancelación de este servicio? Seguirá activo hasta su vencimiento.')">
      {!! scloud_csrf() !!}
      <div class="sc-field"><label>Motivo (opcional)</label><input type="text" name="reason" maxlength="300"></div>
      <button class="sc-btn sc-btn--danger sc-btn--sm">Solicitar cancelación</button>
    </form>
  @endif
</div>
@endif
@endsection
