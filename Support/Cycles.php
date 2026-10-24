<?php

namespace SombrillaCloud\Support;

use Carbon\Carbon;

/** Ciclos de facturación: clave => [etiqueta, meses]. */
final class Cycles
{
    public const ALL = [
        'monthly'    => ['Mensual', 1],
        'quarterly'  => ['Trimestral', 3],
        'semiannual' => ['Semestral', 6],
        'annual'     => ['Anual', 12],
        'biennial'   => ['Bienal', 24],
        'triennial'  => ['Trienal', 36],
    ];

    public static function valid(string $cycle): bool
    {
        return isset(self::ALL[$cycle]);
    }

    public static function label(?string $cycle): string
    {
        return self::ALL[$cycle][0] ?? (string) $cycle;
    }

    public static function months(?string $cycle): int
    {
        return self::ALL[$cycle][1] ?? 1;
    }

    /** Sufijo corto para precios: "/mes", "/año"… */
    public static function suffix(?string $cycle): string
    {
        return match ($cycle) {
            'monthly'    => '/mes',
            'quarterly'  => '/trimestre',
            'semiannual' => '/semestre',
            'annual'     => '/año',
            'biennial'   => '/2 años',
            'triennial'  => '/3 años',
            default      => '',
        };
    }

    /** Suma un ciclo a una fecha sin desbordar fin de mes (31 ene + 1 mes = 28/29 feb). */
    public static function addTo(Carbon|string $date, ?string $cycle): Carbon
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $date->addMonthsNoOverflow(self::months($cycle));
    }
}
