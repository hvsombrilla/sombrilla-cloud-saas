<?php

namespace SombrillaCloud\Services;

use SombrillaCloud\Models\Plan;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Support\Tenancy;

/**
 * Límites de la instancia según su plan: usuarios, plugins activos, tamaño del storage y qué plugins puede usar
 * (todos, solo una selección, o todos menos algunos).
 *
 * El panel principal escribe {storage de la instancia}/limits.json (firmado con scloud.tenant_secret) y, al
 * atender una petición de la instancia, Tenancy::boot() lo lee y lo deja en Config limits.*, que es lo único que
 * conoce el core (Studio\Services\Limits). Así la instancia no consulta ninguna otra base de datos y un core
 * instalado sin Sombrilla Cloud no tiene límites.
 */
final class PlanLimits
{
    public const MODES = ['all' => 'Todos los plugins', 'only' => 'Solo una selección', 'except' => 'Todos menos algunos'];

    /** Límites del plan, solo las claves con valor (lo demás = sin límite). */
    public static function forPlan(Plan $plan): array
    {
        $out = [];
        foreach (['max_users' => 'users', 'max_plugins' => 'plugins', 'storage_mb' => 'storage_mb'] as $column => $key) {
            if ((int) $plan->{$column} > 0) {
                $out[$key] = (int) $plan->{$column};
            }
        }
        $mode = (string) $plan->plugins_mode;
        if (isset(self::MODES[$mode]) && $mode !== 'all') {
            $out['plugins_mode'] = $mode;
            $out['plugins_list'] = $plan->pluginList();
        }

        return $out;
    }

    /** Escribe (o borra, si no hay límites) el archivo de la instancia. */
    public static function write(string $slug, array $limits): bool
    {
        $file = Tenancy::dir($slug) . '/limits.json';
        if (!$limits) {
            return !is_file($file) || @unlink($file);
        }
        $tmp = $file . '.tmp';

        return @file_put_contents($tmp, json_encode(['limits' => $limits, 'sig' => Tenancy::sign($slug, $limits)]), LOCK_EX) !== false && @rename($tmp, $file);
    }

    public static function syncService(Service $service): bool
    {
        $service->loadMissing('plan');
        if (!$service->subdomain || !$service->plan || !Tenancy::exists($service->subdomain)) {
            return false;
        }

        return self::write($service->subdomain, self::forPlan($service->plan));
    }

    /** Aplica los límites actuales del plan a todas sus instancias. @return int instancias actualizadas */
    public static function syncPlan(Plan $plan): int
    {
        $n = 0;
        $limits = self::forPlan($plan);
        foreach (Service::where('plan_id', $plan->id)->whereIn('status', ['active', 'suspended'])->whereNotNull('subdomain')->get() as $service) {
            if (Tenancy::exists($service->subdomain) && self::write($service->subdomain, $limits)) {
                $n++;
            }
        }

        return $n;
    }

    /** Plugins que vienen activados en una instancia nueva, filtrados por lo que su plan permite y su cupo. */
    public static function defaultPlugins(array $defaults, array $limits): array
    {
        $mode = $limits['plugins_mode'] ?? 'all';
        $list = $limits['plugins_list'] ?? [];
        $defaults = array_values(array_filter(array_map('strval', $defaults), fn($slug) => match ($mode) {
            'only'   => in_array($slug, $list, true),
            'except' => !in_array($slug, $list, true),
            default  => true,
        }));

        return isset($limits['plugins']) ? array_slice($defaults, 0, (int) $limits['plugins']) : $defaults;
    }

    /** Plugins que una instancia puede ofrecer: los del sistema menos los ocultos del panel principal. @return array<string, array> */
    public static function catalog(): array
    {
        $hidden = array_merge(['sombrilla-cloud'], array_map('strval', (array) Tenancy::cfg('tenant_hidden_plugins', [])));

        return array_diff_key(\Studio\Plugins\PluginManager::discover(), array_flip($hidden));
    }

    /** Resumen legible: «5 usuarios · 3 plugins · 500 MB · 4 plugins permitidos». */
    public static function summary(Plan $plan): string
    {
        $l = self::forPlan($plan);
        $parts = [];
        if (isset($l['users'])) {
            $parts[] = $l['users'] . ($l['users'] === 1 ? ' usuario' : ' usuarios');
        }
        if (isset($l['plugins'])) {
            $parts[] = $l['plugins'] . ($l['plugins'] === 1 ? ' plugin activo' : ' plugins activos');
        }
        if (isset($l['storage_mb'])) {
            $parts[] = $l['storage_mb'] >= 1024 ? rtrim(rtrim(number_format($l['storage_mb'] / 1024, 1), '0'), '.') . ' GB' : $l['storage_mb'] . ' MB';
        }
        if (($l['plugins_mode'] ?? 'all') === 'only') {
            $parts[] = count($l['plugins_list']) . ' plugins permitidos';
        } elseif (($l['plugins_mode'] ?? 'all') === 'except') {
            $parts[] = 'todos los plugins menos ' . count($l['plugins_list']);
        }

        return $parts ? implode(' · ', $parts) : 'Sin límites';
    }
}
