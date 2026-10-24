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
        <div class="sc-field"><label>Categoría</label><input type="text" name="category" value="{{ $v('category') }}" list="scCats"><datalist id="scCats"><option value="Instancias"><option value="Empresas"></datalist></div>
        <div class="sc-field"><label>Eslogan</label><input type="text" name="tagline" value="{{ $v('tagline') }}"></div>
      </div>
      <div class="sc-field"><label>Características (una por línea)</label><textarea name="features" style="min-height:140px" placeholder="5 usuarios&#10;Base de datos propia&#10;Soporte por tickets">{{ $v('features') }}</textarea></div>
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
    <div class="sc-card"><h3>Límites de la instancia — deja vacío lo que no quieras limitar</h3>
      <div class="sc-grid3">
        <div class="sc-field"><label>Usuarios</label><input type="number" min="1" name="max_users" value="{{ $v('max_users') }}" placeholder="Sin límite"></div>
        <div class="sc-field"><label>Plugins activos</label><input type="number" min="1" name="max_plugins" value="{{ $v('max_plugins') }}" placeholder="Sin límite"><small>Las dependencias cuentan.</small></div>
        <div class="sc-field"><label>Almacenamiento (MB)</label><input type="number" min="1" name="storage_mb" value="{{ $v('storage_mb') }}" placeholder="Sin límite"><small>Archivos + base de datos de la instancia.</small></div>
      </div>
      @php
        $catalog  = \SombrillaCloud\Services\PlanLimits::catalog();
        $mode     = scloud_old('plugins_mode', $plan->plugins_mode ?: 'all');
        $selected = (array) scloud_old('plugins_list', $plan->pluginList());
      @endphp
      <div class="sc-field"><label>Plugins que puede usar</label>
        <select name="plugins_mode" id="plMode">@foreach(\SombrillaCloud\Services\PlanLimits::MODES as $k => $label)<option value="{{ $k }}" {{ $mode === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select>
      </div>
      <div id="plPicker" style="display:{{ $mode === 'all' ? 'none' : 'block' }}">
        <div style="display:flex;gap:8px;align-items:center;margin:6px 0 10px;flex-wrap:wrap">
          <input type="search" id="plSearch" placeholder="Buscar plugin…" style="flex:1 1 200px;min-width:0">
          <button type="button" class="sc-btn sc-btn--ghost sc-btn--sm" id="plAll">Marcar todos</button>
          <button type="button" class="sc-btn sc-btn--ghost sc-btn--sm" id="plNone">Ninguno</button>
          <span class="sc-muted" id="plCount" style="font-size:.85rem"></span>
        </div>
        <p class="sc-muted" style="font-size:.85rem;margin:0 0 8px" id="plHelp"></p>
        <div id="plGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:6px;max-height:300px;overflow:auto;padding:2px">
          @foreach($catalog as $slug => $p)
            <label class="sc-check" data-name="{{ strtolower($p['name'] . ' ' . $slug) }}" style="margin:0;padding:8px 10px;border:1px solid var(--border);border-radius:8px;align-items:flex-start">
              <input type="checkbox" name="plugins_list[]" value="{{ $slug }}" {{ in_array($slug, $selected, true) ? 'checked' : '' }}>
              <span><b style="font-weight:600">{{ $p['name'] }}</b><br><small class="sc-muted">{{ $slug }}</small></span>
            </label>
          @endforeach
        </div>
      </div>
      <script>
        (function () {
          var mode = document.getElementById('plMode'), box = document.getElementById('plPicker'), grid = document.getElementById('plGrid');
          var help = document.getElementById('plHelp'), count = document.getElementById('plCount');
          var boxes = function () { return grid.querySelectorAll('input[type=checkbox]'); };
          function paint() {
            box.style.display = mode.value === 'all' ? 'none' : 'block';
            help.textContent = mode.value === 'only' ? 'La instancia solo verá y podrá activar los plugins marcados.' : 'La instancia verá todos los plugins, excepto los marcados.';
            var n = grid.querySelectorAll('input:checked').length;
            count.textContent = n + ' de ' + boxes().length + ' marcados';
          }
          mode.addEventListener('change', paint);
          grid.addEventListener('change', paint);
          document.getElementById('plAll').addEventListener('click', function () { boxes().forEach(function (b) { if (b.closest('label').style.display !== 'none') b.checked = true; }); paint(); });
          document.getElementById('plNone').addEventListener('click', function () { boxes().forEach(function (b) { b.checked = false; }); paint(); });
          document.getElementById('plSearch').addEventListener('input', function () {
            var q = this.value.toLowerCase();
            grid.querySelectorAll('label').forEach(function (l) { l.style.display = l.getAttribute('data-name').indexOf(q) === -1 ? 'none' : ''; });
          });
          paint();
        })();
      </script>
    </div>
  </div>
  <div>
    <div class="sc-card"><h3>Facturación</h3>
      <div class="sc-field"><label>ID de producto en el ERP (opcional)</label><input type="number" name="product_id" value="{{ $v('product_id') }}"><small>Vincula las líneas de factura con un producto de servicio del ERP.</small></div>
    </div>
    <div class="sc-card"><h3>Visibilidad</h3>
      <label class="sc-check"><input type="checkbox" name="is_active" value="1" {{ scloud_old('is_active', $plan->exists ? $plan->is_active : true) ? 'checked' : '' }}> Activo (se puede contratar)</label>
      <label class="sc-check"><input type="checkbox" name="is_hidden" value="1" {{ scloud_old('is_hidden', $plan->is_hidden) ? 'checked' : '' }}> Oculto en el catálogo (solo por enlace directo)</label>
      <label class="sc-check"><input type="checkbox" name="is_featured" value="1" {{ scloud_old('is_featured', $plan->is_featured) ? 'checked' : '' }}> Destacado («Más popular»)</label>
      <div class="sc-field"><label>Orden</label><input type="number" name="sort_order" value="{{ $v('sort_order', 0) }}"></div>
    </div>
    <div class="sc-actions"><button class="sc-btn">Guardar plan</button></div>
  </div>
  </div>
</form>
@if($plan->exists)
<form method="post" action="{{ url('/scloud/planes/' . $plan->id . '/eliminar') }}" onsubmit="return confirm('¿Eliminar el plan? Si tiene servicios, solo se desactivará.')" style="margin-top:8px">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--danger sc-btn--sm">Eliminar plan</button></form>
@endif
@if($plan->exists)
<div style="margin-top:16px;">
  @include('studio::partials.chatter', ['entity' => 'scloud_plans', 'id' => $plan->id])
</div>
@endif
@endsection
