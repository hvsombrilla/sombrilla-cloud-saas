<?php

namespace SombrillaCloud\Models;

use Studio\Models\Model;

class ServiceLog extends Model
{
    protected $table = 'scloud_logs';

    protected $casts = ['ok' => 'boolean'];

    public static function write(?int $serviceId, string $action, bool $ok, ?string $message = null, ?int $customerId = null): void
    {
        try {
            self::create([
                'service_id'  => $serviceId,
                'customer_id' => $customerId,
                'action'      => $action,
                'ok'          => $ok,
                'message'     => $message !== null ? mb_substr($message, 0, 5000) : null,
                'user_id'     => function_exists('ss_session_user_id') ? (ss_session_user_id() ?: null) : null,
            ]);
        } catch (\Throwable) {
            // el log nunca debe interrumpir una operación
        }
    }
}
