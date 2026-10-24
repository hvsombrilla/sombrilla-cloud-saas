@extends('scloud::site.layout')
@section('title', 'Carrito — ' . scloud_setting('brand_name'))

@section('content')
<div class="sc-page">
  <div class="sc-wrap">
    <div class="sc-page-head"><h1>Tu carrito</h1></div>
    @if(!$items)
      <div class="sc-card sc-empty"><p>Tu carrito está vacío.</p><a class="sc-btn" href="{{ url('/planes') }}">Ver planes</a></div>
    @else
      <div class="sc-layout">
        <div class="sc-card">
          @foreach($items as $item)
            <div class="sc-line" style="align-items:center">
              <div>
                <strong>{{ $item['plan']->name }}</strong>
                <div class="sc-muted" style="font-size:.88rem">{{ $item['workspace'] }} · {{ \SombrillaCloud\Support\Cycles::label($item['cycle']) }}</div>
                <div class="sc-muted" style="font-size:.88rem">@if(($item['mode'] ?? 'subdomain') === 'subdomain'){{ \SombrillaCloud\Support\Tenancy::host($item['subdomain']) }}@elseif(($item['mode'] ?? '') === 'buy')Dominio nuevo: {{ $item['domain'] }}@else Tu dominio: {{ $item['domain'] }}@endif</div>
                @if($item['domain_charge'] > 0)<div class="sc-faint" style="font-size:.82rem">+ dominio {{ scloud_money($item['domain_charge']) }} (1 año)</div>@endif
                @if($item['setup'] > 0)<div class="sc-faint" style="font-size:.82rem">+ instalación {{ scloud_money($item['setup']) }}</div>@endif
              </div>
              <div class="sc-right">
                <strong>{{ scloud_money($item['price'] + $item['domain_charge']) }}</strong>
                <form method="post" action="{{ url('/carrito/' . $item['key'] . '/quitar') }}">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ghost sc-btn--sm" style="margin-top:6px">Quitar</button></form>
              </div>
            </div>
          @endforeach
          <p style="margin:16px 0 0"><a href="{{ url('/planes') }}">+ Agregar otro plan</a></p>
        </div>
        <aside class="sc-card" style="position:sticky;top:84px">
          <h3>Resumen</h3>
          <div class="sc-line"><span>Subtotal</span><span>{{ scloud_money($totals['subtotal']) }}</span></div>
          <div class="sc-line"><span>{{ scloud_setting('tax_label') }} ({{ $totals['rate'] }}%)</span><span>{{ scloud_money($totals['tax']) }}</span></div>
          <div class="sc-total"><span>Total</span><span>{{ scloud_money($totals['total']) }}</span></div>
          <a class="sc-btn sc-btn--lg sc-btn--block" style="margin-top:18px" href="{{ url('/checkout') }}">Continuar al checkout</a>
        </aside>
      </div>
    @endif
  </div>
</div>
@endsection
