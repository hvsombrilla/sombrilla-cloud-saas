<?php

namespace SombrillaCloud\Controllers\Site;

use Illuminate\Http\Request;
use SombrillaCloud\Services\Cloudflare;
use SombrillaCloud\Services\CloudflareException;
use SombrillaCloud\Services\DomainService;
use SombrillaCloud\Services\OrderService;
use SombrillaCloud\Support\Tenancy;

/** Endpoints JSON del paso «Tu dominio»: disponibilidad de subdominios y búsqueda de dominios. */
class DomainController
{
    /** GET /dominio/subdominio?s=acme */
    public function subdomain(Request $request)
    {
        if ($limit = $this->throttle('sub', 40)) {
            return $limit;
        }
        $slug  = strtolower(trim((string) $request->query('s')));
        $error = $slug === '' ? 'Escribe el subdominio que quieres.' : DomainService::subdomainError($slug);

        return response()->json([
            'ok'        => true,
            'available' => $error === null,
            'host'      => $slug !== '' ? Tenancy::host($slug) : null,
            'message'   => $error ?? '¡Disponible! ' . Tenancy::host($slug),
        ]);
    }

    /** GET /dominio/buscar?q=miempresa  |  q=miempresa.com */
    public function search(Request $request)
    {
        if (!Cloudflare::enabled()) {
            return response()->json(['ok' => false, 'message' => 'La compra de dominios no está disponible.'], 404);
        }
        if ($limit = $this->throttle('dom', 15)) {
            return $limit;
        }
        $q = strtolower(trim((string) $request->query('q')));
        if ($q === '' || strlen($q) > 100) {
            return response()->json(['ok' => false, 'message' => 'Escribe el nombre que buscas.']);
        }

        try {
            $results = [];
            $exact   = str_contains($q, '.') ? OrderService::normalizeDomain($q) : null;
            if ($exact) {
                $results = Cloudflare::check([$exact]);
            }
            $keyword = $exact ? explode('.', $exact)[0] : $q;
            foreach (Cloudflare::search($keyword, 8) as $r) {
                if (!$exact || $r['name'] !== $exact) {
                    $results[] = $r;
                }
            }
        } catch (CloudflareException $e) {
            return response()->json(['ok' => false, 'message' => 'No pudimos consultar la disponibilidad ahora. Inténtalo de nuevo en unos minutos.']);
        }

        $out = [];
        foreach ($results as $r) {
            if ($r['name'] === '') {
                continue;
            }
            $takenHere = DomainService::domainError($r['name']) !== null;
            $out[] = [
                'name'      => $r['name'],
                'available' => $r['registrable'] && !$takenHere,
                'price'     => $r['price'],
                'price_fmt' => $r['price'] !== null ? scloud_money($r['price']) : null,
            ];
        }

        return response()->json(['ok' => true, 'results' => $out]);
    }

    /** Límite por sesión (peticiones por minuto): evita abusar de la API de Cloudflare. */
    private function throttle(string $bucket, int $max)
    {
        scloud_session();
        $now  = time();
        $hits = array_filter($_SESSION['scloud_throttle'][$bucket] ?? [], fn($t) => $t > $now - 60);
        if (count($hits) >= $max) {
            return response()->json(['ok' => false, 'message' => 'Demasiadas consultas. Espera un momento.'], 429);
        }
        $hits[] = $now;
        $_SESSION['scloud_throttle'][$bucket] = $hits;

        return null;
    }
}
