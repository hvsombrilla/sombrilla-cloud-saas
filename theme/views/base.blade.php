@php
  $saas  = function_exists('scloud_setting');
  $brand = $saas ? scloud_setting('brand_name') : $webs->name();
  $wa    = $saas ? preg_replace('/\D/', '', (string) scloud_setting('whatsapp')) : '';
  $path  = '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
  $me    = $saas ? scloud_customer() : null;
  $cart  = $saas ? \StudioSaas\Support\Cart::count() : 0;
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }}{{ $title !== '' && $title !== $brand ? ' — ' . $brand : '' }}</title>
  @if($description !== '')<meta name="description" content="{{ $description }}">@endif
  {!! $head !!}
</head>
<body class="{{ $bodyClass }} theme-sombrilla-cloud">
<header class="sc-header" id="scHeader">
  <div class="sc-wrap">
    <a class="sc-logo" href="{{ url('/') }}"><i>☂</i> {{ $brand }}</a>
    <button class="sc-burger" type="button" aria-label="Menú" onclick="document.getElementById('scHeader').classList.toggle('is-open')">☰</button>
    <nav class="sc-nav">
      <a href="{{ url('/') }}" class="{{ $path === '/' ? 'is-on' : '' }}">Inicio</a>
      <a href="{{ url('/planes') }}" class="{{ str_starts_with($path, '/planes') || str_starts_with($path, '/contratar') ? 'is-on' : '' }}">Planes</a>
      <a href="{{ url('/contacto') }}" class="{{ str_starts_with($path, '/contacto') ? 'is-on' : '' }}">Contacto</a>
    </nav>
    @if($saas)
    <div class="sc-actions">
      <a class="sc-btn sc-btn--ghost sc-btn--sm sc-cart" href="{{ url('/carrito') }}">🛒 Carrito @if($cart)<b>{{ $cart }}</b>@endif</a>
      @if($me)
        <a class="sc-btn sc-btn--sm" href="{{ url('/cliente') }}">Mi cuenta</a>
      @else
        <a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/cliente/login') }}">Ingresar</a>
        <a class="sc-btn sc-btn--sm" href="{{ url('/cliente/registro') }}">Crear cuenta</a>
      @endif
    </div>
    @endif
  </div>
</header>

@php($flash = $webs->flash())
@if($flash)
  <div class="sc-wrap" style="padding-top:20px">@foreach($flash as $type => $msg)<div class="sc"><div class="sc-alert {{ $type === 'error' ? 'sc-alert--bad' : 'sc-alert--ok' }}">{{ $msg }}</div></div>@endforeach</div>
@endif

<main class="w-main">
  @yield('body')
</main>

<footer class="sc-footer">
  <div class="sc-wrap">
    <div class="cols">
      <div>
        <a class="sc-logo" href="{{ url('/') }}" style="color:#fff!important"><i>☂</i> {{ $brand }}</a>
        @if($saas)<p style="margin-top:12px;max-width:380px">{{ scloud_setting('brand_tagline') }}</p>
        @if(scloud_setting('address'))<p>{{ scloud_setting('address') }}</p>@endif @endif
      </div>
      <div>
        <h4>Producto</h4>
        <ul><li><a href="{{ url('/planes') }}">Planes</a></li><li><a href="{{ url('/carrito') }}">Carrito</a></li><li><a href="{{ url('/contacto') }}">Contacto</a></li></ul>
      </div>
      <div>
        <h4>Clientes</h4>
        <ul>
          <li><a href="{{ url('/cliente') }}">Mi panel</a></li><li><a href="{{ url('/cliente/tickets') }}">Soporte</a></li>
          @if($saas && scloud_setting('terms_url'))<li><a href="{{ scloud_setting('terms_url') }}" target="_blank" rel="noopener">Términos del servicio</a></li>@endif
          @if($saas && scloud_setting('privacy_url'))<li><a href="{{ scloud_setting('privacy_url') }}" target="_blank" rel="noopener">Privacidad</a></li>@endif
        </ul>
      </div>
    </div>
    <div class="copy">© {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.</div>
  </div>
</footer>
@if($wa)<a class="sc-wa" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">WhatsApp</a>@endif
{!! $foot !!}
</body>
</html>
