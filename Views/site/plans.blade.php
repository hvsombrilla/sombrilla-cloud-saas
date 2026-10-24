@extends('scloud::site.layout')
@section('title', 'Planes de hosting — ' . scloud_setting('brand_name'))

@section('content')
<div class="sc-page">
  <div class="sc-wrap">
    <div class="sc-page-head sc-center">
      <h1>Planes de hosting</h1>
      <p>Precios en {{ scloud_setting('currency_symbol') }}. Más ahorro mientras más largo el ciclo.</p>
      @if(count($cycles) > 1)
        <div class="sc-tabs" id="scTabs" style="margin-top:20px">
          @foreach($cycles as $key => $label)<button type="button" data-cycle="{{ $key }}" class="{{ $loop->first || $key === 'monthly' ? '' : '' }}">{{ $label }}</button>@endforeach
        </div>
      @endif
    </div>

    @forelse($groups as $category => $plans)
      <h2 class="sc-cat">{{ $category }}</h2>
      @foreach(array_keys($cycles) as $cycleKey)
        <div class="sc-plans" data-cycle-group="{{ $cycleKey }}" hidden>
          @foreach($plans as $plan)
            @if($plan->price($cycleKey) !== null) @include('scloud::site._plan-card', ['plan' => $plan, 'cycle' => $cycleKey]) @endif
          @endforeach
        </div>
      @endforeach
    @empty
      <div class="sc-card sc-empty">Pronto publicaremos nuestros planes.</div>
    @endforelse
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var tabs = document.querySelectorAll('#scTabs button'), groups = document.querySelectorAll('[data-cycle-group]');
  var keys = Array.prototype.map.call(groups, function (g) { return g.dataset.cycleGroup; });
  function show(c) {
    groups.forEach(function (g) { g.hidden = g.dataset.cycleGroup !== c; });
    tabs.forEach(function (t) { t.classList.toggle('is-on', t.dataset.cycle === c); });
    try { localStorage.setItem('scCycle', c); } catch (e) {}
  }
  var start = 'monthly'; try { start = localStorage.getItem('scCycle') || start; } catch (e) {}
  if (keys.indexOf(start) < 0) start = keys.indexOf('monthly') >= 0 ? 'monthly' : keys[0];
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.cycle); }); });
  if (start) show(start);
})();
</script>
@endpush
