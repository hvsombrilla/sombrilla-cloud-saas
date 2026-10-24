@extends('scloud::site.layout')

@section('content')
<section class="sc-hero">
  <div class="sc-wrap">
    <div>
      @if(scloud_setting('hero_badge'))<span class="sc-badge">{{ scloud_setting('hero_badge') }}</span>@endif
      <h1>{{ scloud_setting('hero_title') }}</h1>
      <p class="lead">{{ scloud_setting('hero_subtitle') }}</p>
      <div class="sc-cta">
        <a class="sc-btn sc-btn--lg" href="{{ url('/planes') }}">Ver planes</a>
        <a class="sc-btn sc-btn--lg sc-btn--ghost" href="{{ url('/contacto') }}">Hablar con ventas</a>
      </div>
    </div>
    <div class="sc-hero-art" aria-hidden="true">
      <div>$ <b>create</b> instance miempresa</div>
      <div>→ base de datos <em>propia</em> creada</div>
      <div>→ administrador <b>listo</b> · SSL <b>activo</b></div>
      <div>→ <em>https://miempresa.sombrilla.cloud</em></div>
      <div>✓ Entras ya con la sesión iniciada</div>
    </div>
  </div>
</section>

<section class="sc-section">
  <div class="sc-wrap">
    <div class="sc-section-head">
      <h2>Todo lo que tu operación necesita</h2>
      <p>Tu propio Sombrilla Studio, sin instalar nada y con soporte en tu idioma.</p>
    </div>
    <div class="sc-features">
      <div class="sc-feature"><div class="ico">⚡</div><h3>Activación automática</h3><p>Al confirmar tu pago, tu instancia se crea sola y recibes los accesos por correo.</p></div>
      <div class="sc-feature"><div class="ico">🔒</div><h3>SSL gratis</h3><p>Tu subdominio incluye certificado SSL, y también puedes usar tu propio dominio.</p></div>
      <div class="sc-feature"><div class="ico">💾</div><h3>Datos aislados</h3><p>Cada instancia tiene su propia base de datos y su propio almacenamiento.</p></div>
      <div class="sc-feature"><div class="ico">✉️</div><h3>Tu dominio</h3><p>Elige un subdominio, apunta el tuyo con un simple CNAME o cómpralo con nosotros.</p></div>
      <div class="sc-feature"><div class="ico">🛟</div><h3>Soporte con tickets</h3><p>Seguimiento de cada solicitud desde tu panel de cliente.</p></div>
      <div class="sc-feature"><div class="ico">🏦</div><h3>Paga por transferencia</h3><p>Transfiere a nuestra cuenta, sube el comprobante y activamos tu servicio.</p></div>
    </div>
  </div>
</section>

@if($plans->count())
<section class="sc-section sc-section--alt">
  <div class="sc-wrap">
    <div class="sc-section-head"><h2>{{ $category ?: 'Nuestros planes' }}</h2><p>Elige el plan que mejor se ajusta a tu proyecto. Puedes cambiar de plan cuando crezcas.</p></div>
    <div class="sc-plans">@foreach($plans as $plan) @include('scloud::site._plan-card', ['plan' => $plan]) @endforeach</div>
    <p class="sc-center" style="margin-top:28px"><a class="sc-btn sc-btn--ghost" href="{{ url('/planes') }}">Comparar todos los planes →</a></p>
  </div>
</section>
@endif

<section class="sc-section">
  <div class="sc-wrap">
    <div class="sc-section-head"><h2>Así de fácil es empezar</h2></div>
    <div class="sc-features">
      <div class="sc-feature"><div class="ico">1</div><h3>Elige tu plan</h3><p>Selecciona el plan y el ciclo de facturación que prefieras.</p></div>
      <div class="sc-feature"><div class="ico">2</div><h3>Transfiere</h3><p>Recibes los datos bancarios y subes el comprobante desde tu panel.</p></div>
      <div class="sc-feature"><div class="ico">3</div><h3>Validamos</h3><p>Aprobamos tu pago y emitimos tu factura.</p></div>
      <div class="sc-feature"><div class="ico">4</div><h3>Listo</h3><p>Tu instancia se crea automáticamente, te llegan los accesos y entras con un clic.</p></div>
    </div>
  </div>
</section>
@endsection
