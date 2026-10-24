@extends('scloud::site.layout')
@section('title', 'Checkout — ' . scloud_setting('brand_name'))

@section('content')
<div class="sc-page">
  <div class="sc-wrap">
    <div class="sc-page-head"><h1>Finalizar pedido</h1><p>Pago por transferencia bancaria. Tu servicio se activa al validar el comprobante.</p></div>
    <form method="post" action="{{ url('/checkout') }}" class="sc-layout">
      {!! scloud_csrf() !!}
      <div>
        @if($customer)
          <div class="sc-card">
            <h3>Cliente</h3>
            <p style="margin:0"><strong>{{ $customer->name }}</strong><br><span class="sc-muted">{{ $customer->email }}</span></p>
          </div>
        @else
          <div class="sc-card">
            <h3>Tu cuenta</h3>
            <div class="sc-radio" style="margin-bottom:18px">
              <label><input type="radio" name="account" value="register" {{ scloud_old('account', 'register') === 'register' ? 'checked' : '' }}> Soy cliente nuevo</label>
              <label><input type="radio" name="account" value="login" {{ scloud_old('account') === 'login' ? 'checked' : '' }}> Ya tengo cuenta</label>
            </div>
            <div id="scRegister">
              <div class="sc-grid2">
                <div class="sc-field"><label>Nombre completo *</label><input type="text" name="name" value="{{ scloud_old('name') }}" autocomplete="name"></div>
                <div class="sc-field"><label>Empresa</label><input type="text" name="company" value="{{ scloud_old('company') }}" autocomplete="organization"></div>
                <div class="sc-field"><label>Correo electrónico *</label><input type="email" name="email" value="{{ scloud_old('email') }}" autocomplete="email"></div>
                <div class="sc-field"><label>Teléfono</label><input type="tel" name="phone" value="{{ scloud_old('phone') }}" autocomplete="tel"></div>
                <div class="sc-field"><label>RNC / Cédula (para la factura)</label><input type="text" name="tax_id" value="{{ scloud_old('tax_id') }}"></div>
                <div class="sc-field"><label>Ciudad</label><input type="text" name="city" value="{{ scloud_old('city') }}"></div>
                <div class="sc-field"><label>Contraseña * (mín. 8)</label><input type="password" name="password" autocomplete="new-password"></div>
                <div class="sc-field"><label>Repite la contraseña *</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
              </div>
            </div>
            <div id="scLogin" hidden>
              <div class="sc-grid2">
                <div class="sc-field"><label>Correo</label><input type="email" name="login_email" value="{{ scloud_old('login_email') }}" autocomplete="username"></div>
                <div class="sc-field"><label>Contraseña</label><input type="password" name="login_password" autocomplete="current-password"></div>
              </div>
              <a href="{{ url('/cliente/recuperar') }}">¿Olvidaste tu contraseña?</a>
            </div>
          </div>
        @endif

        <div class="sc-card">
          <h3>Método de pago: transferencia bancaria</h3>
          <p class="sc-muted">{{ scloud_setting('transfer_instructions') }}</p>
          @forelse($banks as $bank)
            <div class="sc-bank"><strong>{{ $bank->bank }}</strong>
              <dl><dt>Tipo</dt><dd>{{ $bank->account_type }} ({{ $bank->currency }})</dd><dt>Cuenta</dt><dd class="sc-mono">{{ $bank->account_number }}</dd><dt>Titular</dt><dd>{{ $bank->holder }}@if($bank->holder_id) · {{ $bank->holder_id }}@endif</dd></dl>
            </div>
          @empty
            <div class="sc-alert sc-alert--info">Te enviaremos los datos bancarios al confirmar el pedido.</div>
          @endforelse
        </div>

        <div class="sc-card">
          <div class="sc-field"><label>Notas del pedido (opcional)</label><textarea name="notes" style="min-height:80px">{{ scloud_old('notes') }}</textarea></div>
          <label class="sc-check"><input type="checkbox" name="accept_terms" value="1" {{ scloud_old('accept_terms') ? 'checked' : '' }}>
            <span>Acepto los @if(scloud_setting('terms_url'))<a href="{{ scloud_setting('terms_url') }}" target="_blank" rel="noopener">términos del servicio</a>@else términos del servicio @endif y la política de privacidad.</span></label>
        </div>
      </div>

      <aside class="sc-card" style="position:sticky;top:84px">
        <h3>Tu pedido</h3>
        @foreach($items as $item)
          <div class="sc-line"><span>{{ $item['plan']->name }}<br><small class="sc-muted">{{ $item['workspace'] }} · {{ \SombrillaCloud\Support\Cycles::label($item['cycle']) }}@if(($item['mode'] ?? 'subdomain') === 'subdomain') · {{ \SombrillaCloud\Support\Tenancy::host($item['subdomain']) }}@elseif($item['domain']) · {{ $item['domain'] }}@endif</small></span><span>{{ scloud_money($item['price'] + $item['setup']) }}</span></div>
          @if($item['domain_charge'] > 0)<div class="sc-line"><span><small class="sc-muted">Dominio {{ $item['domain'] }} (1 año)</small></span><span>{{ scloud_money($item['domain_charge']) }}</span></div>@endif
        @endforeach
        <div class="sc-line"><span>Subtotal</span><span>{{ scloud_money($totals['subtotal']) }}</span></div>
        <div class="sc-line"><span>{{ scloud_setting('tax_label') }} ({{ $totals['rate'] }}%)</span><span>{{ scloud_money($totals['tax']) }}</span></div>
        <div class="sc-total"><span>Total</span><span>{{ scloud_money($totals['total']) }}</span></div>
        <button class="sc-btn sc-btn--lg sc-btn--block" style="margin-top:18px" type="submit">Confirmar pedido</button>
        <p class="sc-faint sc-center" style="font-size:.82rem;margin:12px 0 0"><a href="{{ url('/carrito') }}">← Volver al carrito</a></p>
      </aside>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var r = document.querySelectorAll('input[name=account]'), reg = document.getElementById('scRegister'), log = document.getElementById('scLogin');
  if (!reg) return;
  function sync() { var v = document.querySelector('input[name=account]:checked'); v = v ? v.value : 'register'; reg.hidden = v !== 'register'; log.hidden = v !== 'login'; }
  r.forEach(function (x) { x.addEventListener('change', sync); }); sync();
})();
</script>
@endpush
