@php
  $brand = scloud_setting('brand_name');
  $wa    = preg_replace('/\D/', '', (string) scloud_setting('whatsapp'));
  $path  = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  $me    = scloud_customer();
  $__ok  = session('success');
  $__err = session('error');
  $__errs = scloud_errors();
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', $brand . ' — ' . scloud_setting('brand_tagline'))</title>
<meta name="description" content="@yield('description', scloud_setting('hero_subtitle'))">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ scloud_asset('site.css') }}">
@stack('head')
</head>
<body class="sc">
<header class="sc-header" id="scHeader">
  <div class="sc-wrap">
    <a class="sc-logo" href="{{ url('/') }}"><i>☂</i> {{ $brand }}</a>
    <button class="sc-burger" type="button" aria-label="Menú" onclick="document.getElementById('scHeader').classList.toggle('is-open')">☰</button>
    <nav class="sc-nav">
      <a href="{{ url('/') }}" class="{{ $path === '/' ? 'is-on' : '' }}">Inicio</a>
      <a href="{{ url('/planes') }}" class="{{ str_starts_with($path, '/planes') || str_starts_with($path, '/contratar') ? 'is-on' : '' }}">Planes</a>
      <a href="{{ url('/contacto') }}" class="{{ str_starts_with($path, '/contacto') ? 'is-on' : '' }}">Contacto</a>
    </nav>
    <div class="sc-actions">
      <a class="sc-btn sc-btn--ghost sc-btn--sm sc-cart" href="{{ url('/carrito') }}">🛒 Carrito @if(\SombrillaCloud\Support\Cart::count())<b>{{ \SombrillaCloud\Support\Cart::count() }}</b>@endif</a>
      @if($me)
        <a class="sc-btn sc-btn--sm" href="{{ url('/cliente') }}">Mi cuenta</a>
      @else
        <a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/cliente/login') }}">Ingresar</a>
        <a class="sc-btn sc-btn--sm" href="{{ url('/cliente/registro') }}">Crear cuenta</a>
      @endif
    </div>
  </div>
</header>

<main>
  @if($__ok || $__err || $__errs)
    <div class="sc-wrap" style="padding-top:20px">
      @if($__ok)<div class="sc-alert sc-alert--ok">{{ $__ok }}</div>@endif
      @if($__err)<div class="sc-alert sc-alert--bad">{{ $__err }}</div>@endif
      @if($__errs)<div class="sc-alert sc-alert--bad"><ul>@foreach($__errs as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    </div>
  @endif
  @yield('content')
</main>

<footer class="sc-footer">
  <div class="sc-wrap">
    <div class="cols">
      <div>
        <a class="sc-logo" href="{{ url('/') }}" style="color:#fff!important"><i>☂</i> {{ $brand }}</a>
        <p style="margin-top:12px;max-width:380px">{{ scloud_setting('brand_tagline') }}</p>
        @if(scloud_setting('address'))<p>{{ scloud_setting('address') }}</p>@endif
      </div>
      <div>
        <h4>Producto</h4>
        <ul><li><a href="{{ url('/planes') }}">Planes</a></li><li><a href="{{ url('/carrito') }}">Carrito</a></li><li><a href="{{ url('/contacto') }}">Contacto</a></li></ul>
      </div>
      <div>
        <h4>Clientes</h4>
        <ul>
          <li><a href="{{ url('/cliente') }}">Mi panel</a></li><li><a href="{{ url('/cliente/tickets') }}">Soporte</a></li>
          @if(scloud_setting('terms_url'))<li><a href="{{ scloud_setting('terms_url') }}" target="_blank" rel="noopener">Términos del servicio</a></li>@endif
          @if(scloud_setting('privacy_url'))<li><a href="{{ scloud_setting('privacy_url') }}" target="_blank" rel="noopener">Privacidad</a></li>@endif
        </ul>
      </div>
    </div>
    <div class="copy">© {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.</div>
  </div>
</footer>
@if($wa)<a class="sc-wa" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">WhatsApp</a>@endif
@stack('scripts')
</body>
</html>
