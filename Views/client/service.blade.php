@extends('scloud::client.layout')
@section('title', $service->title() . ' — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><p><a href="{{ url('/cliente/servicios') }}">← Mis servicios</a></p><h1 style="font-size:1.6rem">{{ $service->title() }} {!! scloud_chip($service->statusLabel(), $service->statusTone()) !!}</h1></div>

@if($service->status === 'suspended')<div class="sc-alert sc-alert--bad">Este servicio está suspendido{{ $service->suspend_reason ? ': ' . $service->suspend_reason : '' }}. @if($renewal && $renewal->invoice)<a href="{{ url('/cliente/facturas/' . $renewal->invoice_id) }}">Paga tu factura {{ $renewal->invoice->code }}</a> para reactivarlo.@endif</div>@endif
@if($service->status === 'pending')<div class="sc-alert sc-alert--info">Tu servicio se activará automáticamente cuando validemos el pago de tu pedido @if($service->order)<a href="{{ url('/cliente/pedidos/' . $service->order->code) }}">{{ $service->order->code }}</a>@endif.</div>@endif
@if($service->status === 'failed')<div class="sc-alert sc-alert--warn">Estamos terminando de configurar tu instancia. Nuestro equipo ya fue notificado; si pasa mucho tiempo, <a href="{{ url('/cliente/tickets/nuevo?servicio=' . $service->id) }}">abre un ticket</a>.</div>@endif
@if($renewal && $renewal->invoice && in_array($renewal->invoice->status, ['issued', 'partial', 'overdue', 'draft'], true) && $service->status === 'active')<div class="sc-alert sc-alert--warn">Tienes una factura de renovación pendiente: <a href="{{ url('/cliente/facturas/' . $renewal->invoice_id) }}">{{ $renewal->invoice->code }}</a> por {{ scloud_money($renewal->invoice->balance) }}.</div>@endif

<div class="sc-card">
  <div class="sc-card-head"><h3>Tu instancia</h3>
    @if($service->status === 'active')<form method="post" action="{{ url('/cliente/servicios/' . $service->id . '/abrir') }}">{!! scloud_csrf() !!}<button class="sc-btn">Abrir mi instancia →</button></form>@endif</div>
  <dl class="sc-kv">
    <dt>Workspace</dt><dd>{{ $service->workspace_name ?: '—' }}</dd>
    <dt>Plan</dt><dd>{{ $service->plan->name ?? '—' }}</dd>
    @if($service->subdomain)<dt>Dirección</dt><dd><a href="{{ $service->publicUrl() }}" target="_blank" rel="noopener">{{ $service->publicUrl() }}</a>@if($service->domain && $service->domain_status === 'connected')<div class="sc-faint" style="font-size:.85rem">También en {{ $service->host() }}</div>@endif</dd>@endif
    @if($service->admin_email)<dt>Administrador</dt><dd>{{ $service->admin_email }}</dd>@endif
    @if($service->domain)<dt>Dominio</dt><dd>{{ $service->domain }} {!! scloud_chip($service->domainStatusLabel(), $service->domainStatusTone()) !!}@if($service->domain_expires_at)<div class="sc-faint" style="font-size:.85rem">Vence {{ scloud_date($service->domain_expires_at) }}</div>@endif</dd>@endif
    <dt>Ciclo</dt><dd>{{ $service->cycleLabel() }} · {{ scloud_money($service->amount) }} <span class="sc-faint">+ impuestos</span></dd>
    <dt>Registrado</dt><dd>{{ scloud_date($service->registration_date) }}</dd>
    <dt>Próximo vencimiento</dt><dd>{{ scloud_date($service->next_due_date) }}</dd>
  </dl>
  @if($service->status === 'active')<p class="sc-muted" style="font-size:.88rem;margin:14px 0 0">El botón te lleva a tu instancia ya con la sesión iniciada. Si olvidaste tu contraseña, entra desde aquí y cámbiala en tu perfil de la instancia.</p>@endif
</div>

@if($dns && $service->domain_status !== 'connected' && $service->isLive())
<div class="sc-card">
  <h3>Conecta tu dominio {{ $service->domain }}</h3>
  <p class="sc-muted">Entra al panel DNS de tu dominio (donde lo compraste) y crea {{ count($dns['records']) > 1 ? 'estos registros' : 'este registro' }}:</p>
  <div class="sc-table-wrap"><table class="sc-table">
    <thead><tr><th>Tipo</th><th>Nombre</th><th>Valor</th><th></th></tr></thead>
    <tbody>
    @foreach($dns['records'] as $r)
      <tr><td><strong>{{ $r['type'] }}</strong></td><td class="sc-mono">{{ $r['name'] }}</td><td class="sc-mono">{{ $r['value'] }}</td>
          <td><button type="button" class="sc-btn sc-btn--ghost sc-btn--sm" data-copy="{{ $r['value'] }}">Copiar</button></td></tr>
      <tr><td colspan="4" class="sc-faint" style="font-size:.85rem;padding-top:0">{{ $r['note'] }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
  <p class="sc-muted" style="font-size:.88rem">Los cambios de DNS pueden tardar desde unos minutos hasta unas horas. Mientras tanto tu instancia funciona en <strong>{{ $service->host() }}</strong>.</p>
  <form method="post" action="{{ url('/cliente/servicios/' . $service->id . '/dominio/verificar') }}">{!! scloud_csrf() !!}<button class="sc-btn">Verificar ahora</button></form>
</div>
@push('scripts')
<script>document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy); b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = 'Copiar'; }, 1500); }); });</script>
@endpush
@elseif($service->domain_status === 'registering' || $service->domain_status === 'dns_pending')
<div class="sc-alert sc-alert--info">Estamos {{ $service->domain_status === 'registering' ? 'registrando' : 'conectando' }} tu dominio <strong>{{ $service->domain }}</strong>. Te avisaremos cuando esté listo; tu instancia ya funciona en {{ $service->host() }}.</div>
@elseif($service->domain_status === 'failed')
<div class="sc-alert sc-alert--warn">No pudimos completar el dominio <strong>{{ $service->domain }}</strong>. Nuestro equipo ya fue notificado; <a href="{{ url('/cliente/tickets/nuevo?servicio=' . $service->id) }}">abre un ticket</a> si necesitas ayuda. Tu instancia funciona en {{ $service->host() }}.</div>
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
