<?php

use SombrillaCloud\Models\Customer;
use SombrillaCloud\Support\Settings;

/** Renderiza una vista del plugin: scloud_view('site.home'). */
function scloud_view(string $view, array $data = [])
{
    return view('scloud::' . $view, $data);
}

/** URL de un asset del plugin con cache-busting. */
function scloud_asset(string $path): string
{
    $path = ltrim($path, '/');
    $v    = @filemtime(SCLOUD_DIR . 'assets/' . $path);

    return rtrim(SOMBRILLA_STUDIO_URL, '/') . SCLOUD_URL . 'assets/' . $path . ($v ? '?v=' . $v : '');
}

function scloud_setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

/** "RD$ 1,250.00" con el símbolo configurado. */
function scloud_money($amount, bool $symbol = true): string
{
    $n = number_format((float) $amount, 2, '.', ',');

    return $symbol ? trim((string) Settings::get('currency_symbol', 'RD$')) . ' ' . $n : $n;
}

/** Precio compacto para tarjetas: "RD$ 299" o "RD$ 299.50". */
function scloud_price($amount): string
{
    $amount = (float) $amount;
    $n = fmod($amount, 1.0) == 0.0 ? number_format($amount, 0, '.', ',') : number_format($amount, 2, '.', ',');

    return trim((string) Settings::get('currency_symbol', 'RD$')) . ' ' . $n;
}

function scloud_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/** Cliente autenticado en el panel de clientes, o null. */
function scloud_customer(): ?Customer
{
    static $cached = false;
    scloud_session();
    $id = (int) ($_SESSION['scloud_customer_id'] ?? 0);
    if (!$id) {
        return null;
    }
    if ($cached === false || ($cached && $cached->id !== $id)) {
        $cached = Customer::find($id);
    }

    return $cached ?: null;
}

function scloud_login(Customer $customer): void
{
    scloud_session();
    session_regenerate_id(true);
    $_SESSION['scloud_customer_id'] = $customer->id;
    $customer->last_login_at = now();
    $customer->last_login_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $customer->save();
}

function scloud_logout(): void
{
    scloud_session();
    unset($_SESSION['scloud_customer_id']);
}

/** Guarda el input para repoblar el formulario en la siguiente petición. */
function scloud_with_input(array $input): void
{
    scloud_session();
    unset($input['_token'], $input['password'], $input['password_confirmation'], $input['current_password']);
    $_SESSION['_flash']['scloud_old'] = $input;
}

/** Valor anterior de un campo (tras un error de validación). */
function scloud_old(string $key, mixed $default = null): mixed
{
    static $old = null;
    if ($old === null) {
        $old = session('scloud_old') ?: [];
    }

    return $old[$key] ?? $default;
}

/** Errores de validación: scloud_errors(['...']) para guardar, scloud_errors() para leer. */
function scloud_errors(?array $set = null): array
{
    static $errors = null;
    if ($set !== null) {
        scloud_session();
        $_SESSION['_flash']['scloud_errors'] = array_values($set);

        return $set;
    }
    if ($errors === null) {
        $errors = session('scloud_errors') ?: [];
    }

    return $errors;
}

/** Redirige con errores e input: return scloud_back_with('/cliente/registro', $errors, $request->all()). */
function scloud_back_with(string $to, array $errors, array $input = [])
{
    scloud_errors($errors);
    if ($input) {
        scloud_with_input($input);
    }

    return redirect(url($to));
}

/** <span class="sc-chip sc-chip--ok">Activo</span> */
function scloud_chip(string $label, string $tone = ''): string
{
    return '<span class="sc-chip' . ($tone ? ' sc-chip--' . e($tone) : '') . '">' . e($label) . '</span>';
}

/** Estado de una factura del ERP para mostrar al cliente: [etiqueta, tono]. */
function scloud_invoice_status($invoice): array
{
    if ($invoice->status === 'draft' || in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
        $overdue = $invoice->due_date && $invoice->due_date->lt(now()->startOfDay());
        if ($invoice->status === 'partial') {
            return ['Pago parcial', 'warn'];
        }

        return $overdue ? ['Vencida', 'bad'] : ['Pendiente de pago', 'warn'];
    }

    return match ($invoice->status) {
        'paid'      => ['Pagada', 'ok'],
        'cancelled' => ['Anulada', ''],
        default     => [(string) $invoice->status, ''],
    };
}

/** Tamaño legible: 1536 → "1.5 GB" (entrada en MB). */
function scloud_mb($mb): string
{
    if ($mb === null || $mb === '' || $mb === 'unlimited') {
        return 'Ilimitado';
    }
    $mb = (float) preg_replace('/[^0-9.]/', '', (string) $mb);

    return $mb >= 1024 ? rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.') . ' GB' : round($mb) . ' MB';
}

/** Paginador simple (anterior / siguiente) con los parámetros de la consulta. */
function scloud_pager($paginator): string
{
    if (!$paginator->hasPages()) {
        return '';
    }
    $prev = $paginator->previousPageUrl();
    $next = $paginator->nextPageUrl();

    return '<div class="sc-pager">'
        . ($prev ? '<a class="sc-btn sc-btn--ghost sc-btn--sm" href="' . e($prev) . '">← Anterior</a>' : '<span></span>')
        . '<span class="sc-muted">Página ' . $paginator->currentPage() . ' de ' . $paginator->lastPage() . '</span>'
        . ($next ? '<a class="sc-btn sc-btn--ghost sc-btn--sm" href="' . e($next) . '">Siguiente →</a>' : '<span></span>')
        . '</div>';
}

function scloud_csrf(): string
{
    return '<input type="hidden" name="_token" value="' . e(ss_csrf_token()) . '">';
}

/** Fecha corta dd/mm/aaaa (acepta Carbon, string o null). */
function scloud_date($date, string $empty = '—'): string
{
    if (!$date) {
        return $empty;
    }
    try {
        return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
    } catch (\Throwable) {
        return $empty;
    }
}

/** Página 404 propia (el helper abort() del framework no está disponible en este contenedor). */
function scloud_not_found(): never
{
    http_response_code(404);
    echo scloud_view('site.404')->render();
    exit;
}
