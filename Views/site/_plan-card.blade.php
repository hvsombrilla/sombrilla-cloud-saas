{{-- Tarjeta de plan. Variables: $plan, $cycle (ciclo mostrado, opcional) --}}
@php
  $cycles = $plan->cycles();
  $cycle  = isset($cycle) && isset($cycles[$cycle]) ? $cycle : $plan->defaultCycle();
  $price  = $cycle ? $cycles[$cycle] : null;
@endphp
<article class="sc-plan {{ $plan->is_featured ? 'is-featured' : '' }}">
  @if($plan->is_featured)<span class="ribbon">Más popular</span>@endif
  <h3>{{ $plan->name }}</h3>
  <div class="tag">{{ $plan->tagline }}</div>
  @if($price !== null)
    <div class="sc-price"><strong>{{ scloud_price($price) }}</strong> <span>{{ \SombrillaCloud\Support\Cycles::suffix($cycle) }}</span></div>
    <div class="sub">
      @if($cycle !== 'monthly' && ($m = $plan->monthlyEquivalent($cycle))) Equivale a {{ scloud_price($m) }}/mes @if($plan->savings($cycle) > 0)· ahorras {{ $plan->savings($cycle) }}%@endif @endif
      @if($plan->setup_fee > 0) {{ $cycle !== 'monthly' ? '·' : '' }} + {{ scloud_price($plan->setup_fee) }} de instalación @endif
    </div>
  @endif
  <ul>@foreach($plan->featureList() as $f)<li>{{ $f }}</li>@endforeach</ul>
  <a class="sc-btn sc-btn--block {{ $plan->is_featured ? '' : 'sc-btn--ghost' }}" href="{{ url('/contratar/' . $plan->slug) . ($cycle ? '?ciclo=' . $cycle : '') }}">Contratar</a>
</article>
