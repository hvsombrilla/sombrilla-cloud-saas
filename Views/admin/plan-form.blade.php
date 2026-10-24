@extends('scloud::admin.layout')
@section('title', $plan->exists ? 'Plan ' . $plan->name : 'Nuevo plan')
@php $v = fn($k, $d = null) => scloud_old($k, $plan->getAttribute($k) ?? $d); @endphp

@section('scloud')
<div class="sca-head"><h1>{{ $plan->exists ? $plan->name : 'Nuevo plan' }}</h1><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/scloud/planes') }}">← Planes</a></div>
<form method="post" action="{{ url($plan->exists ? '/scloud/planes/' . $plan->id : '/scloud/planes') }}">
  {!! scloud_csrf() !!}
  <div class="sc-cols-21">
  <div>
    <div class="sc-card"><h3>General</h3>
      <div class="sc-grid2">
        <div class="sc-field"><label>Nombre *</label><input type="text" name="name" value="{{ $v('name') }}" required></div>
        <div class="sc-field"><label>Identificador (URL)</label><input type="text" name="slug" value="{{ $v('slug') }}" placeholder="se genera del nombre"></div>
        <div class="sc-field"><label>Categoría</label><input type="text" name="category" value="{{ $v('category') }}" list="scCats"><datalist id="scCats"><option value="Hosting compartido"><option value="Hosting WordPress"><option value="Revendedores"><option value="VPS"></datalist></div>
        <div class="sc-field"><label>Eslogan</label><input type="text" name="tagline" value="{{ $v('tagline') }}"></div>
      </div>
      <div class="sc-field"><label>Características (una por línea)</label><textarea name="features" style="min-height:140px" placeholder="10 GB de disco NVMe&#10;5 cuentas de correo&#10;SSL gratis">{{ $v('features') }}</textarea></div>
      <div class="sc-field"><label>Descripción (se muestra al contratar)</label><textarea name="description">{{ $v('description') }}</textarea></div>
    </div>
    <div class="sc-card"><h3>Precios (sin impuestos) — deja vacío el ciclo que no ofrezcas</h3>
      <div class="sc-grid3">
        @foreach(\SombrillaCloud\Support\Cycles::ALL as $k => [$label])
          <div class="sc-field"><label>{{ $label }}</label><input type="number" step="0.01" min="0" name="price_{{ $k }}" value="{{ $v('price_' . $k) }}"></div>
        @endforeach
        <div class="sc-field"><label>Costo de instalación (único)</label><input type="number" step="0.01" min="0" name="setup_fee" value="{{ $v('setup_fee', 0) }}"></div>
      </div>
    </div>
  </div>
  <div>
    <div class="sc-card"><h3>Aprovisionamiento WHM</h3>
      <div class="sc-field"><label>Servidor</label><select name="server_id" id="scServer"><option value="">Automático (el de menos cuentas)</option>@foreach($servers as $s)<option value="{{ $s->id }}" {{ (int) $v('server_id') === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
      <div class="sc-field"><label>Paquete de WHM</label><input type="text" name="whm_package" id="scPackage" value="{{ $v('whm_package') }}" list="scPkgs" placeholder="Nombre exacto del paquete"><datalist id="scPkgs"></datalist>
        <small id="scPkgMsg">Elige un servidor para cargar sus paquetes.</small></div>
      <div class="sc-field"><label>ID de producto en el ERP (opcional)</label><input type="number" name="product_id" value="{{ $v('product_id') }}"><small>Vincula las líneas de factura con un producto de servicio del ERP.</small></div>
    </div>
    <div class="sc-card"><h3>Visibilidad</h3>
      <label class="sc-check"><input type="checkbox" name="is_active" value="1" {{ scloud_old('is_active', $plan->exists ? $plan->is_active : true) ? 'checked' : '' }}> Activo (se puede contratar)</label>
      <label class="sc-check"><input type="checkbox" name="is_hidden" value="1" {{ scloud_old('is_hidden', $plan->is_hidden) ? 'checked' : '' }}> Oculto en el catálogo (solo por enlace directo)</label>
      <label class="sc-check"><input type="checkbox" name="is_featured" value="1" {{ scloud_old('is_featured', $plan->is_featured) ? 'checked' : '' }}> Destacado («Más popular»)</label>
      <label class="sc-check"><input type="checkbox" name="requires_domain" value="1" {{ scloud_old('requires_domain', $plan->exists ? $plan->requires_domain : true) ? 'checked' : '' }}> Requiere dominio</label>
      <div class="sc-field"><label>Orden</label><input type="number" name="sort_order" value="{{ $v('sort_order', 0) }}"></div>
    </div>
    <div class="sc-actions"><button class="sc-btn">Guardar plan</button></div>
  </div>
  </div>
</form>
@if($plan->exists)
<form method="post" action="{{ url('/scloud/planes/' . $plan->id . '/eliminar') }}" onsubmit="return confirm('¿Eliminar el plan? Si tiene servicios, solo se desactivará.')" style="margin-top:8px">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--danger sc-btn--sm">Eliminar plan</button></form>
@endif
@endsection

@push('scripts')
<script>
(function () {
  var sel = document.getElementById('scServer'), list = document.getElementById('scPkgs'), msg = document.getElementById('scPkgMsg');
  function load() {
    list.innerHTML = '';
    if (!sel.value) { msg.textContent = 'Elige un servidor para cargar sus paquetes.'; return; }
    msg.textContent = 'Cargando paquetes de WHM…';
    fetch('{{ url('/scloud/servidores') }}/' + sel.value + '/paquetes', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { msg.textContent = 'No se pudo leer WHM: ' + d.error; return; }
        d.packages.forEach(function (p) { var o = document.createElement('option'); o.value = p.name; o.label = p.name + (p.quota ? ' · ' + p.quota + ' MB' : ''); list.appendChild(o); });
        msg.textContent = d.packages.length + ' paquete(s) disponibles en WHM: escribe o selecciona uno.';
      }).catch(function () { msg.textContent = 'No se pudo consultar WHM.'; });
  }
  sel.addEventListener('change', load); load();
})();
</script>
@endpush
