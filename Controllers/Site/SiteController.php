<?php

namespace SombrillaCloud\Controllers\Site;

use SombrillaCloud\Models\Plan;

class SiteController
{
    public function home()
    {
        $plans = Plan::visible()->get();
        $featuredCategory = $plans->first()?->category;

        return scloud_view('site.home', [
            'plans'    => $plans->where('category', $featuredCategory)->take(4)->values(),
            'category' => $featuredCategory,
        ]);
    }

    public function plans()
    {
        $plans = Plan::visible()->get();

        return scloud_view('site.plans', [
            'groups' => $plans->groupBy('category'),
            'cycles' => $this->offeredCycles($plans),
        ]);
    }

    public function contact()
    {
        return scloud_view('site.contact');
    }

    /** Ciclos que ofrece al menos un plan (para el selector de la página de planes). */
    private function offeredCycles($plans): array
    {
        $out = [];
        foreach ($plans as $plan) {
            foreach (array_keys($plan->cycles()) as $c) {
                $out[$c] = \SombrillaCloud\Support\Cycles::label($c);
            }
        }
        $order = array_keys(\SombrillaCloud\Support\Cycles::ALL);
        uksort($out, fn($a, $b) => array_search($a, $order) <=> array_search($b, $order));

        return $out;
    }
}
