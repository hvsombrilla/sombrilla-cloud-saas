@extends('scloud::site.layout')
@section('title', 'Contratar ' . $plan->name . ' — ' . scloud_setting('brand_name'))

@section('content')
<div class="sc-page">
  <div class="sc-wrap">
    <div class="sc-page-head"><h1>Contratar {{ $plan->name }}</h1><p>{{ $plan->tagline }}</p></div>
    <form method="post" action="{{ url('/carrito') }}" class="sc-layout" id="scConfig">
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
        <div class="sc-card">
          <h3>2. Tu workspace</h3>
          <div class="sc-field">
            <label for="workspace">Nombre del workspace</label>
            <input id="workspace" type="text" name="workspace" maxlength="60" placeholder="Mi Empresa, S.R.L." value="{{ scloud_old('workspace') }}" required autocomplete="organization">
            <small>Así se llamará tu espacio de trabajo dentro de la aplicación. Podrás cambiarlo después.</small>
          </div>
        </div>
        <div class="sc-card">
          <h3>3. Tu dirección</h3>
          @php $mode = scloud_old('mode', 'subdomain'); if (!in_array($mode, $modes, true)) $mode = 'subdomain'; @endphp
          <div class="sc-radio" id="scModes">
            <label><input type="radio" name="mode" value="subdomain" {{ $mode === 'subdomain' ? 'checked' : '' }}><strong>Elegir un subdominio</strong><br><span class="sc-muted">tuempresa.{{ $baseDomain }} · gratis e inmediato</span></label>
            @if(in_array('buy', $modes, true))<label><input type="radio" name="mode" value="buy" {{ $mode === 'buy' ? 'checked' : '' }}><strong>Comprar un dominio</strong><br><span class="sc-muted">Lo registramos y lo conectamos por ti</span></label>@endif
            @if(in_array('own', $modes, true))<label><input type="radio" name="mode" value="own" {{ $mode === 'own' ? 'checked' : '' }}><strong>Ya tengo un dominio</strong><br><span class="sc-muted">Lo apuntas con un CNAME</span></label>@endif
          </div>

          <div class="sc-field" data-mode="subdomain" style="margin-top:16px">
            <label for="subdomain">Subdominio</label>
            <div style="display:flex;align-items:center;gap:8px"><input id="subdomain" type="text" name="subdomain" maxlength="30" placeholder="tuempresa" value="{{ scloud_old('subdomain') }}" autocomplete="off" autocapitalize="none" spellcheck="false" style="flex:1"><span class="sc-muted">.{{ $baseDomain }}</span></div>
            <small id="scSubMsg">Letras minúsculas, números y guiones.</small>
          </div>

          @if(in_array('buy', $modes, true))
          <div class="sc-field" data-mode="buy" style="margin-top:16px">
            <label for="buyq">Busca tu dominio</label>
            <div style="display:flex;gap:8px"><input id="buyq" type="text" placeholder="miempresa o miempresa.com" autocomplete="off" style="flex:1"><button type="button" class="sc-btn sc-btn--ghost" id="scBuySearch">Buscar</button></div>
            <input type="hidden" name="buy_domain" id="buyDomain" value="{{ scloud_old('buy_domain') }}">
            <div id="scBuyResults" style="margin-top:10px"></div>
            <small id="scBuyChosen">{{ scloud_old('buy_domain') ? 'Elegido: ' . scloud_old('buy_domain') : 'Elige un dominio de la lista. El precio es por el primer año.' }}</small>
          </div>
          @endif

          @if(in_array('own', $modes, true))
          <div class="sc-field" data-mode="own" style="margin-top:16px">
            <label for="own_domain">Tu dominio</label>
            <input id="own_domain" type="text" name="own_domain" placeholder="miempresa.com" value="{{ scloud_old('own_domain') }}" autocomplete="off">
            <small>Te daremos el registro CNAME que debes crear en el DNS de tu dominio. Tu instancia funciona de inmediato en una dirección <strong>.{{ $baseDomain }}</strong> mientras tanto.</small>
          </div>
          @endif
        </div>
        <div class="sc-card"><h3>Incluye</h3><ul style="margin:0;padding-left:20px">@foreach($plan->featureList() as $f)<li>{{ $f }}</li>@endforeach</ul>@if($plan->description)<p class="sc-muted" style="margin-top:14px;white-space:pre-line">{{ $plan->description }}</p>@endif</div>
      </div>
      <aside class="sc-card" style="position:sticky;top:84px">
        <h3>Resumen</h3>
        <div class="sc-line"><span>{{ $plan->name }}</span><strong id="scPrice">{{ scloud_money($plan->price($cycle) ?? 0) }}</strong></div>
        @if($plan->setup_fee > 0)<div class="sc-line"><span>Instalación (único)</span><strong>{{ scloud_money($plan->setup_fee) }}</strong></div>@endif
        <div class="sc-line" id="scDomLine" hidden><span id="scDomName">Dominio (1 año)</span><strong id="scDomPrice"></strong></div>
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
<script>
(function () {
  var form = document.getElementById('scConfig'); if (!form) return;
  function $(s, c) { return (c || document).querySelector(s); }
  var panels = form.querySelectorAll('[data-mode]');
  function mode() { var r = $('input[name=mode]:checked', form); return r ? r.value : 'subdomain'; }
  function sync() { var m = mode(); panels.forEach(function (p) { p.hidden = p.dataset.mode !== m; }); if (m !== 'buy') { $('#scDomLine').hidden = true; } else { showDomain(); } }
  form.querySelectorAll('input[name=mode]').forEach(function (r) { r.addEventListener('change', sync); });

  // Disponibilidad del subdominio
  var sub = $('#subdomain'), msg = $('#scSubMsg'), t;
  if (sub) sub.addEventListener('input', function () {
    sub.value = sub.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    clearTimeout(t);
    if (!sub.value) { msg.textContent = 'Letras minúsculas, números y guiones.'; msg.className = ''; return; }
    msg.textContent = 'Comprobando…'; msg.className = '';
    t = setTimeout(function () {
      fetch(@json(url('/dominio/subdominio')) + '?s=' + encodeURIComponent(sub.value), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { msg.textContent = d.message || ''; msg.style.color = d.available ? 'var(--ok, #16a34a)' : 'var(--bad, #dc2626)'; })
        .catch(function () { msg.textContent = ''; });
    }, 350);
  });

  // Búsqueda y compra de dominio
  var q = $('#buyq'), btn = $('#scBuySearch'), box = $('#scBuyResults'), hidden = $('#buyDomain'), chosen = $('#scBuyChosen'), price = {};
  function showDomain() {
    var line = $('#scDomLine'); if (!hidden || !hidden.value || !price[hidden.value]) { line.hidden = true; return; }
    $('#scDomName').textContent = 'Dominio ' + hidden.value + ' (1 año)'; $('#scDomPrice').textContent = price[hidden.value]; line.hidden = false;
  }
  function search() {
    if (!q.value.trim()) return;
    box.textContent = 'Buscando…';
    fetch(@json(url('/dominio/buscar')) + '?q=' + encodeURIComponent(q.value.trim()), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        box.innerHTML = '';
        if (!d.ok) { box.textContent = d.message || 'No se pudo buscar.'; return; }
        if (!d.results.length) { box.textContent = 'Sin resultados. Prueba con otro nombre.'; return; }
        d.results.forEach(function (r) {
          var row = document.createElement('div'); row.className = 'sc-line'; row.style.alignItems = 'center';
          var name = document.createElement('span'); name.textContent = r.name;
          var right = document.createElement('span');
          if (r.available) {
            price[r.name] = r.price_fmt;
            var b = document.createElement('button'); b.type = 'button'; b.className = 'sc-btn sc-btn--sm'; b.textContent = 'Elegir · ' + r.price_fmt + '/año';
            b.addEventListener('click', function () { hidden.value = r.name; chosen.textContent = 'Elegido: ' + r.name + ' — ' + r.price_fmt + ' el primer año'; showDomain(); });
            right.appendChild(b);
          } else { right.className = 'sc-muted'; right.textContent = 'No disponible'; }
          row.appendChild(name); row.appendChild(right); box.appendChild(row);
        });
      }).catch(function () { box.textContent = 'No se pudo buscar. Inténtalo de nuevo.'; });
  }
  if (btn) { btn.addEventListener('click', search); q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } }); }

  form.addEventListener('submit', function (e) {
    if (mode() === 'buy' && hidden && !hidden.value) { e.preventDefault(); chosen.textContent = 'Busca y elige un dominio para continuar.'; }
  });
  sync();
})();
</script>
@endpush
