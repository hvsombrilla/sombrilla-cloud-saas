<?php

namespace SombrillaCloud\Support;

use Studio\Models\SsOption;

/** Ajustes del plugin, guardados como un solo arreglo en ss_options (scloud_settings). */
final class Settings
{
    private const OPTION = 'scloud_settings';

    private static ?array $cache = null;

    public const DEFAULTS = [
        // General
        'brand_name'           => 'Sombrilla Cloud',
        'brand_tagline'        => 'Hosting rápido, seguro y con soporte local',
        'support_email'        => '',
        'support_phone'        => '',
        'whatsapp'             => '',
        'address'              => 'Santo Domingo, República Dominicana',
        'currency_symbol'      => 'RD$',
        'terms_url'            => '',
        'privacy_url'          => '',
        // Portada
        'hero_title'           => 'Hosting que no te deja bajo la lluvia',
        'hero_subtitle'        => 'Planes cPanel con discos NVMe, SSL gratis, correos profesionales y soporte en español. Tu sitio activo en minutos.',
        'hero_badge'           => 'Servidores optimizados · Uptime 99.9%',
        // Facturación
        'tax_rate'             => 18,
        'tax_label'            => 'ITBIS',
        'invoice_due_days'     => 3,    // días para pagar la factura de un pedido nuevo
        'invoice_days_before'  => 7,    // generar la factura de renovación N días antes del vencimiento
        'suspend_after_days'   => 5,    // suspender N días después del vencimiento si sigue sin pagar
        'terminate_after_days' => 0,    // terminar N días después del vencimiento (0 = nunca, manual)
        'transfer_instructions' => 'Realiza la transferencia por el monto exacto de la factura e indica el número de factura en la descripción. Luego sube el comprobante desde tu panel para que validemos el pago.',
        // Aprovisionamiento
        'auto_provision'       => '1',  // crear la cuenta en WHM al confirmar el pago
        'username_length'      => 8,
        // Seguridad / tareas
        'cron_key'             => '',
        // Correo
        'mail_from_name'       => 'Sombrilla Cloud',
        'mail_from_email'      => '',
        'admin_notify_email'   => '',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                $stored = SsOption::get(self::OPTION, []);
            } catch (\Throwable) {
                $stored = [];
            }
            self::$cache = array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);
        }

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function bool(string $key): bool
    {
        $v = self::get($key);

        return $v === true || $v === 1 || $v === '1' || $v === 'on';
    }

    public static function int(string $key): int
    {
        return (int) self::get($key, 0);
    }

    /** Guarda solo las claves conocidas. */
    public static function save(array $values): void
    {
        $current = self::all();
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                $current[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        SsOption::set(self::OPTION, $current);
        self::$cache = $current;
    }

    /** Llave secreta del cron; se genera la primera vez. */
    public static function cronKey(): string
    {
        $key = (string) self::get('cron_key', '');
        if ($key === '') {
            $key = bin2hex(random_bytes(16));
            try {
                self::save(['cron_key' => $key]);
            } catch (\Throwable) {
                // sin base de datos todavía
            }
        }

        return $key;
    }
}
