<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Services\PlanLimits;
use SombrillaCloud\Support\Cycles;

class PlanController
{
    public function index()
    {
        $plans  = Plan::orderBy('sort_order')->orderBy('id')->get();
        $counts = Service::whereIn('status', ['active', 'suspended', 'pending'])->selectRaw('plan_id, count(*) as n')->groupBy('plan_id')->pluck('n', 'plan_id');

        return scloud_view('admin.plans', ['plans' => $plans, 'counts' => $counts]);
    }

    public function create()
    {
        return scloud_view('admin.plan-form', ['plan' => new Plan(['is_active' => true, 'category' => 'Instancias'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        if (is_string($data)) {
            return scloud_back_with('/scloud/planes/nuevo', [$data], $request->all());
        }
        $plan = Plan::create($data);
        flash('success', 'Plan creado.');

        return redirect(url('/scloud/planes/' . $plan->id));
    }

    public function edit(int $id)
    {
        return scloud_view('admin.plan-form', ['plan' => Plan::findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $plan = Plan::findOrFail($id);
        $data = $this->validated($request, $plan);
        if (is_string($data)) {
            return scloud_back_with('/scloud/planes/' . $id, [$data], $request->all());
        }
        $plan->update($data);
        // Los límites del plan viajan a las instancias que ya lo tienen.
        $synced = PlanLimits::syncPlan($plan->fresh());
        flash('success', 'Plan guardado.' . ($synced ? " Límites aplicados a {$synced} " . ($synced === 1 ? 'instancia.' : 'instancias.') : ''));

        return redirect(url('/scloud/planes/' . $id));
    }

    public function destroy(int $id)
    {
        $plan = Plan::findOrFail($id);
        if (Service::where('plan_id', $id)->exists()) {
            $plan->update(['is_active' => false, 'is_hidden' => true]);
            flash('success', 'El plan tiene servicios asociados: se desactivó y ocultó en lugar de eliminarse.');
        } else {
            $plan->delete();
            flash('success', 'Plan eliminado.');
        }

        return redirect(url('/scloud/planes'));
    }

    /** @return array|string datos listos para guardar, o el mensaje de error */
    private function validated(Request $request, ?Plan $plan): array|string
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return 'El nombre del plan es obligatorio.';
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', (string) ($request->input('slug') ?: $name)))), '-');
        if ($slug === '' || Plan::where('slug', $slug)->when($plan, fn($q) => $q->where('id', '!=', $plan->id))->exists()) {
            return 'Ya existe un plan con ese identificador (slug). Usa otro.';
        }

        $prices = [];
        $any    = false;
        foreach (array_keys(Cycles::ALL) as $cycle) {
            $raw = trim((string) $request->input('price_' . $cycle));
            $prices['price_' . $cycle] = $raw === '' ? null : max(0, round((float) str_replace(',', '', $raw), 2));
            $any = $any || $prices['price_' . $cycle] !== null;
        }
        if (!$any) {
            return 'Define el precio de al menos un ciclo de facturación.';
        }

        // Límites de la instancia: vacío = sin límite.
        $limit = fn(string $key, int $max) => ($v = trim((string) $request->input($key))) === '' ? null : (max(0, min($max, (int) $v)) ?: null);
        $catalog = array_keys(PlanLimits::catalog());
        $mode    = array_key_exists((string) $request->input('plugins_mode'), PlanLimits::MODES) ? (string) $request->input('plugins_mode') : 'all';
        $list    = array_values(array_intersect($catalog, array_map('strval', (array) $request->input('plugins_list', []))));
        if ($mode === 'only' && !$list) {
            return 'Elige al menos un plugin permitido, o usa «Todos los plugins».';
        }

        return $prices + [
            'max_users' => $limit('max_users', 100000),
            'max_plugins' => $limit('max_plugins', 1000),
            'storage_mb' => $limit('storage_mb', 10485760),
            'plugins_mode' => $mode,
            'plugins_list' => $mode === 'all' || !$list ? null : json_encode($list),
            'name' => mb_substr($name, 0, 150), 'slug' => $slug,
            'category' => mb_substr(trim((string) $request->input('category')) ?: 'Instancias', 0, 100),
            'tagline' => mb_substr(trim((string) $request->input('tagline')), 0, 250) ?: null,
            'description' => trim((string) $request->input('description')) ?: null,
            'features' => trim((string) $request->input('features')) ?: null,
            'product_id' => (int) $request->input('product_id') ?: null,
            'setup_fee' => max(0, (float) $request->input('setup_fee')),
            'is_featured' => (bool) $request->input('is_featured'),
            'is_active' => (bool) $request->input('is_active'),
            'is_hidden' => (bool) $request->input('is_hidden'),
            'sort_order' => (int) $request->input('sort_order'),
        ];
    }
}
