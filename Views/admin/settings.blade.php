@extends('scloud::admin.layout')
@section('title', 'Ajustes — Sombrilla Cloud')
@php $f = fn($k) => scloud_old($k, $s[$k] ?? ''); @endphp

@section('scloud')
<div class="sca-head"><h1>Ajustes de Sombrilla Cloud</h1></div>
<form method="post" action="{{ url('/scloud/ajustes') }}">
  {!! scloud_csrf() !!}
  <div class="sc-cols">
  <div>
    <div class="sc-card"><h3>Marca y contacto</h3>
      <div class="sc-field"><label>Nombre comercial</label><input type="text" name="brand_name" value="{{ $f('brand_name') }}"></div>
      <div class="sc-field"><label>Eslogan</label><input type="text" name="brand_tagline" value="{{ $f('brand_tagline') }}"></div>
      <div class="sc-grid2"><div class="sc-field"><label>Correo de soporte</label><input type="email" name="support_email" value="{{ $f('support_email') }}"></div><div class="sc-field"><label>Teléfono</label><input type="text" name="support_phone" value="{{ $f('support_phone') }}"></div>
      <div class="sc-field"><label>WhatsApp (con código de país)</label><input type="text" name="whatsapp" value="{{ $f('whatsapp') }}" placeholder="18095551234"></div><div class="sc-field"><label>Moneda (símbolo)</label><input type="text" name="currency_symbol" value="{{ $f('currency_symbol') }}"></div></div>
      <div class="sc-field"><label>Dirección</label><input type="text" name="address" value="{{ $f('address') }}"></div>
      <div class="sc-grid2"><div class="sc-field"><label>URL de términos</label><input type="url" name="terms_url" value="{{ $f('terms_url') }}"></div><div class="sc-field"><label>URL de privacidad</label><input type="url" name="privacy_url" value="{{ $f('privacy_url') }}"></div></div>
    </div>
    <div class="sc-card"><h3>Portada</h3>
      <div class="sc-field"><label>Insignia</label><input type="text" name="hero_badge" value="{{ $f('hero_badge') }}"></div>
      <div class="sc-field"><label>Título</label><input type="text" name="hero_title" value="{{ $f('hero_title') }}"></div>
      <div class="sc-field"><label>Subtítulo</label><textarea name="hero_subtitle" style="min-height:70px">{{ $f('hero_subtitle') }}</textarea></div>
    </div>
    <div class="sc-card"><h3>Correo</h3>
      <div class="sc-grid2"><div class="sc-field"><label>Remitente (nombre)</label><input type="text" name="mail_from_name" value="{{ $f('mail_from_name') }}"></div><div class="sc-field"><label>Remitente (correo)</label><input type="email" name="mail_from_email" value="{{ $f('mail_from_email') }}" placeholder="no-reply@tudominio.com"></div></div>
      <div class="sc-field"><label>Avisar al equipo en</label><input type="email" name="admin_notify_email" value="{{ $f('admin_notify_email') }}"><small>Recibe aviso de pedidos nuevos, comprobantes y tickets.</small></div>
    </div>
  </div>
  <div>
    <div class="sc-card"><h3>Facturación y ciclo de cobro</h3>
      <div class="sc-grid2"><div class="sc-field"><label>Impuesto (%)</label><input type="number" step="0.01" name="tax_rate" value="{{ $f('tax_rate') }}"></div><div class="sc-field"><label>Nombre del impuesto</label><input type="text" name="tax_label" value="{{ $f('tax_label') }}"></div>
      <div class="sc-field"><label>Días para pagar un pedido nuevo</label><input type="number" min="0" name="invoice_due_days" value="{{ $f('invoice_due_days') }}"></div><div class="sc-field"><label>Facturar la renovación N días antes</label><input type="number" min="0" name="invoice_days_before" value="{{ $f('invoice_days_before') }}"></div>
      <div class="sc-field"><label>Suspender N días después del vencimiento</label><input type="number" min="0" name="suspend_after_days" value="{{ $f('suspend_after_days') }}"><small>0 = nunca suspender automáticamente.</small></div><div class="sc-field"><label>Terminar N días después (suspendidos)</label><input type="number" min="0" name="terminate_after_days" value="{{ $f('terminate_after_days') }}"><small>0 = nunca (recomendado: manual).</small></div></div>
      <div class="sc-field"><label>Instrucciones de transferencia</label><textarea name="transfer_instructions">{{ $f('transfer_instructions') }}</textarea></div>
    </div>
    <div class="sc-card"><h3>Aprovisionamiento</h3>
      <label class="sc-check"><input type="checkbox" name="auto_provision" value="1" {{ $s['auto_provision'] === '1' || $s['auto_provision'] === 1 || $s['auto_provision'] === true ? 'checked' : '' }}> Crear la cuenta en WHM automáticamente al confirmar el pago</label>
      <div class="sc-field"><label>Largo del usuario de cPanel</label><input type="number" min="5" max="16" name="username_length" value="{{ $f('username_length') }}"></div>
    </div>
    <div class="sc-card"><h3>Tarea diaria (renovaciones y suspensiones)</h3>
      <p class="sc-muted">Programa este comando en el cron del servidor, una vez al día:</p>
      <input type="text" readonly value="curl -s &quot;{{ $cronUrl }}&quot; &gt; /dev/null" onclick="this.select()" class="sc-mono" style="width:100%">
      <p class="sc-muted" style="margin-bottom:0">Última ejecución: <strong>{{ $cronLast ?: 'nunca' }}</strong></p>
    </div>
  </div>
  </div>
  <div class="sc-actions"><button class="sc-btn">Guardar ajustes</button></div>
</form>

<form method="post" action="{{ url('/scloud/ajustes/cron') }}" style="margin:14px 0 26px" onsubmit="return confirm('¿Ejecutar ahora la tarea de renovaciones, suspensiones y terminaciones?')">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ghost">Ejecutar tarea diaria ahora</button></form>

<div id="bancos">
  <div class="sca-head"><h1 style="font-size:18px">Cuentas bancarias para transferencias</h1></div>
  @foreach($banks as $b)
  <form method="post" action="{{ url('/scloud/ajustes/bancos') }}" class="sc-card">
    {!! scloud_csrf() !!}<input type="hidden" name="id" value="{{ $b->id }}">
    <div class="sc-grid3">
      <div class="sc-field"><label>Banco</label><input type="text" name="bank" value="{{ $b->bank }}" required></div>
      <div class="sc-field"><label>Tipo de cuenta</label><input type="text" name="account_type" value="{{ $b->account_type }}"></div>
      <div class="sc-field"><label>Número de cuenta</label><input type="text" name="account_number" value="{{ $b->account_number }}" required></div>
      <div class="sc-field"><label>Titular</label><input type="text" name="holder" value="{{ $b->holder }}" required></div>
      <div class="sc-field"><label>RNC / Cédula del titular</label><input type="text" name="holder_id" value="{{ $b->holder_id }}"></div>
      <div class="sc-field"><label>Moneda</label><input type="text" name="currency" value="{{ $b->currency }}"></div>
    </div>
    <div class="sc-field"><label>Instrucciones adicionales</label><input type="text" name="instructions" value="{{ $b->instructions }}"></div>
    <div class="sc-actions"><label class="sc-check" style="margin:0"><input type="checkbox" name="is_active" value="1" {{ $b->is_active ? 'checked' : '' }}> Activa</label><input type="number" name="sort_order" value="{{ $b->sort_order }}" style="width:80px" title="Orden"><button class="sc-btn sc-btn--sm">Guardar</button>
      <button class="sc-btn sc-btn--danger sc-btn--sm" formaction="{{ url('/scloud/ajustes/bancos/' . $b->id . '/eliminar') }}" onclick="return confirm('¿Eliminar esta cuenta?')">Eliminar</button></div>
  </form>
  @endforeach
  <form method="post" action="{{ url('/scloud/ajustes/bancos') }}" class="sc-card" style="border-style:dashed">
    {!! scloud_csrf() !!}<h3>+ Nueva cuenta bancaria</h3>
    <div class="sc-grid3">
      <div class="sc-field"><label>Banco *</label><input type="text" name="bank" required placeholder="Banco Popular"></div>
      <div class="sc-field"><label>Tipo de cuenta</label><input type="text" name="account_type" value="Corriente"></div>
      <div class="sc-field"><label>Número de cuenta *</label><input type="text" name="account_number" required></div>
      <div class="sc-field"><label>Titular *</label><input type="text" name="holder" required></div>
      <div class="sc-field"><label>RNC / Cédula del titular</label><input type="text" name="holder_id"></div>
      <div class="sc-field"><label>Moneda</label><input type="text" name="currency" value="DOP"></div>
    </div>
    <input type="hidden" name="is_active" value="1">
    <button class="sc-btn">Agregar cuenta</button>
  </form>
</div>
@endsection
