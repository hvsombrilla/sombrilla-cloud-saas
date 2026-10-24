<?php

namespace SombrillaCloud\Support;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Archivos privados (comprobantes y adjuntos de tickets) en storage/scloud/. Se sirven solo a su dueño. */
final class Files
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    private const ALLOWED = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'zip' => 'application/zip', 'log' => 'text/plain',
    ];

    public static function dir(string $folder): string
    {
        $dir = storage_path('scloud/' . $folder);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @file_put_contents(storage_path('scloud') . '/.htaccess', "Require all denied\n");
        }

        return rtrim($dir, '/');
    }

    /**
     * Guarda un archivo subido. @return array{ok: bool, path?: string, name?: string, error?: string}
     * $images = true solo acepta imágenes y PDF (comprobantes).
     */
    public static function store(?UploadedFile $file, string $folder, bool $images = false): array
    {
        if (!$file) {
            return ['ok' => false, 'error' => 'No se recibió el archivo.'];
        }
        if (!$file->isValid()) {
            return ['ok' => false, 'error' => 'El archivo no se pudo subir (' . $file->getErrorMessage() . ').'];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'El archivo supera los 8 MB.'];
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = $images ? array_intersect_key(self::ALLOWED, array_flip(['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'])) : self::ALLOWED;
        if (!isset($allowed[$ext])) {
            return ['ok' => false, 'error' => 'Tipo de archivo no permitido. Usa: ' . implode(', ', array_keys($allowed)) . '.'];
        }

        $name = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        $file->move(self::dir($folder), $name);

        return ['ok' => true, 'path' => $folder . '/' . $name, 'name' => mb_substr($file->getClientOriginalName(), 0, 200)];
    }

    /** Respuesta con el archivo (inline para imágenes/PDF). */
    public static function response(?string $path, ?string $downloadName = null)
    {
        $path = (string) $path;
        if ($path === '' || str_contains($path, '..') || !preg_match('#^[a-z-]+/[0-9]{8}-[a-f0-9]{20}\.[a-z0-9]+$#', $path)) {
            scloud_not_found();
        }
        $full = storage_path('scloud/' . $path);
        if (!is_file($full)) {
            scloud_not_found();
        }
        $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = self::ALLOWED[$ext] ?? 'application/octet-stream';
        $inline = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'], true);

        return response((string) file_get_contents($full), 200, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($downloadName ?: basename($full)) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=600',
        ]);
    }
}
