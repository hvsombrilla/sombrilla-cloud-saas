<?php

namespace SombrillaCloud\Models;

/** Estados legibles en la auditoría (los modelos guardan STATUSES como [etiqueta, descripción/color]). */
trait AuditsStatus
{
    public function auditFormat(string $field, mixed $value): mixed
    {
        $map = match ($field) {
            'status'        => defined(static::class . '::STATUSES') ? static::STATUSES : [],
            'domain_status' => defined(static::class . '::DOMAIN_STATUSES') ? static::DOMAIN_STATUSES : [],
            default         => [],
        };
        $hit = $map[$value] ?? null;

        return is_array($hit) ? ($hit[0] ?? $value) : ($hit ?? $value);
    }
}
