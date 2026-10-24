@extends('scloud::site.layout')
@section('title', 'Contacto — ' . scloud_setting('brand_name'))

@section('content')
<div class="sc-page">
  <div class="sc-wrap" style="max-width:820px">
    <div class="sc-page-head"><h1>Contacto</h1><p>¿Dudas antes de contratar? Escríbenos y te ayudamos a elegir el plan ideal.</p></div>
    <div class="sc-features">
      @if(scloud_setting('support_email'))<div class="sc-feature"><div class="ico">✉️</div><h3>Correo</h3><p><a href="mailto:{{ scloud_setting('support_email') }}">{{ scloud_setting('support_email') }}</a></p></div>@endif
      @if(scloud_setting('support_phone'))<div class="sc-feature"><div class="ico">📞</div><h3>Teléfono</h3><p><a href="tel:{{ preg_replace('/[^\d+]/', '', scloud_setting('support_phone')) }}">{{ scloud_setting('support_phone') }}</a></p></div>@endif
      @if(scloud_setting('whatsapp'))<div class="sc-feature"><div class="ico">💬</div><h3>WhatsApp</h3><p><a href="https://wa.me/{{ preg_replace('/\D/', '', scloud_setting('whatsapp')) }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a></p></div>@endif
      <div class="sc-feature"><div class="ico">🛟</div><h3>¿Ya eres cliente?</h3><p><a href="{{ url('/cliente/tickets/nuevo') }}">Abre un ticket de soporte</a> desde tu panel.</p></div>
    </div>
    @if(scloud_setting('address'))<p class="sc-center sc-muted" style="margin-top:32px">📍 {{ scloud_setting('address') }}</p>@endif
  </div>
</div>
@endsection
