@extends('scloud::site.layout')
@section('title', 'Contratar ' . $plan->name . ' — ' . scloud_setting('brand_name'))

@section('content')
<div class="sc-page">
  <div class="sc-wrap">
    <div class="sc-page-head"><h1>Contratar {{ $plan->name }}</h1><p>{{ $plan->tagline }}</p></div>
    <form method="post" action="{{ url('/carrito') }}" class="sc-layout">
      {!! scloud_csrf() !!}
      <input type="hidden" name="plan_id" value="{{ $plan->id }}">
      <div>
        <div class="sc-card">
          <h3>1. Ciclo de facturación</h3>
          <div class="sc-radio">
            @foreach($plan->cycles() as $key => $price)
              <label>
                <input type="radio" name="cycle" value="{{ $key }}" data-price="{{ $price }}" {{ scloud_old('cycle', $cycle) === $key ? 'checked' : '' }}>
                <strong>{{ \SombrillaCloud\Support\Cycles::label($key) }}</strong><br>
                <span class="sc-muted">{{ scloud_price($price) }} {{ \SombrillaCloud\Support\Cycles::suffix($key) }}</span>
                @if($plan->savings($key) > 0)<br><span class="sc-chip sc-chip--ok">Ahorras {{ $plan->savings($key) }}%</span>@endif
              </label>
            @endforeach
          </div>
        </div>
        @if($plan->requires_domain)
        <div class="sc-card">
          <h3>2. Tu dominio</h3>
          <div class="sc-field">
            <label for="domain">Dominio principal de la cuenta</label>
            <input id="domain" type="text" name="domain" placeholder="miempresa.com" value="{{ scloud_old('domain') }}" required autocomplete="off">
            <small>Debe estar registrado. Apunta sus DNS a nuestros servidores de nombres una vez que tu cuenta esté activa.</small>
          </div>
        </div>
        @endif
        <div class="sc-card"><h3>Incluye</h3><ul style="margin:0;padding-left:20px">@foreach($plan->featureList() as $f)<li>{{ $f }}</li>@endforeach</ul>@if($plan->description)<p class="sc-muted" style="margin-top:14px;white-space:pre-line">{{ $plan->description }}</p>@endif</div>
      </div>
      <aside class="sc-card" style="position:sticky;top:84px">
        <h3>Resumen</h3>
        <div class="sc-line"><span>{{ $plan->name }}</span><strong id="scPrice">{{ scloud_money($plan->price($cycle) ?? 0) }}</strong></div>
        @if($plan->setup_fee > 0)<div class="sc-line"><span>Instalación (único)</span><strong>{{ scloud_money($plan->setup_fee) }}</strong></div>@endif
        <p class="sc-muted" style="font-size:.85rem;margin:10px 0 16px">{{ scloud_setting('tax_label') }} ({{ scloud_setting('tax_rate') }}%) se calcula en el checkout.</p>
        <button class="sc-btn sc-btn--lg sc-btn--block" type="submit">Agregar al carrito</button>
      </aside>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var sym = @json(trim((string) scloud_setting('currency_symbol')));
  document.querySelectorAll('input[name=cycle]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.getElementById('scPrice').textContent = sym + ' ' + Number(r.dataset.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
  });
})();
</script>
@endpush
