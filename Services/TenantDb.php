<?php

namespace SombrillaCloud\Services;

use SombrillaCloud\Support\Tenancy;
use Studio\Models\Role;
use Studio\Models\SsOption;
use Studio\Models\SsUser;
use Studio\Services\Workspace;

/**
 * Operaciones sobre la SQLite de una instancia desde el panel principal: se abre una conexión temporal,
 * se vuelve por defecto mientras corre el callback (así funcionan los modelos y el esquema del core) y se restaura.
 */
final class TenantDb
{
    /** Ejecuta $fn con la base de datos de la instancia como conexión por defecto. */
    public static function with(string $slug, callable $fn): mixed
    {
        $file = Tenancy::dbFile($slug);
        if (!is_file($file)) {
            throw new \RuntimeException("La base de datos de {$slug} no existe.");
        }

        return self::connect($slug, $file, $fn);
    }

    /** Crea la instancia: SQLite, esquema del core, rol y usuario administrador, nombre del workspace y storage. */
    public static function create(string $slug, string $workspace, string $adminName, string $adminEmail, string $password): void
    {
        $file = Tenancy::dbFile($slug);
        $dir  = Tenancy::dir($slug);
        if (file_exists($file) || file_exists($dir)) {
            throw new \RuntimeException("La instancia {$slug} ya existe.");
        }
        foreach ([dirname($file), $dir] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) {
                throw new \RuntimeException("No se pudo crear el directorio {$d}.");
            }
        }
        if (!@touch($file)) {
            throw new \RuntimeException('No se pudo crear la base de datos de la instancia.');
        }
        @chmod($file, 0660);

        self::connect($slug, $file, function () use ($workspace, $adminName, $adminEmail, $password) {
            \Studio\SchemaManager::createBaseTables();
            \Studio\SchemaManager::migrate();

            $role = Role::firstOrCreate(
                ['slug' => 'admin'],
                ['name' => 'Administrador', 'description' => 'Acceso total al sistema', 'is_admin' => true]
            );
            $admin = SsUser::create([
                'name'      => $adminName,
                'email'     => strtolower($adminEmail),
                'password'  => password_hash($password, PASSWORD_BCRYPT),
                'role_id'   => $role->id,
                'is_active' => true,
            ]);
            $admin->setRoles([$role->id]);
            Workspace::saveName($workspace);
            SsOption::set('ss_active_plugins', array_values((array) Tenancy::cfg('tenant_default_plugins', [])));
        });

        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }

    /** Cambia la contraseña del administrador de la instancia. */
    public static function setAdminPassword(string $slug, string $email, string $password): bool
    {
        return (bool) self::with($slug, function () use ($email, $password) {
            return SsUser::where('email', strtolower($email))->update([
                'password' => password_hash($password, PASSWORD_BCRYPT), 'credentials_changed_at' => now(),
            ]);
        });
    }

    /** Borra todo rastro de una instancia que falló a medias (no toca instancias ajenas: exige el slug exacto). */
    public static function destroy(string $slug): void
    {
        self::removeTree(Tenancy::dir($slug));
        foreach ([Tenancy::dbFile($slug), Tenancy::dbFile($slug) . '-wal', Tenancy::dbFile($slug) . '-shm'] as $f) {
            @unlink($f);
        }
    }

    /** Mueve la instancia a trash/ (recuperable) en lugar de borrarla. */
    public static function archive(string $slug): bool
    {
        $trash = Tenancy::baseRoot() . '/trash';
        if (!is_dir($trash) && !@mkdir($trash, 0775, true) && !is_dir($trash)) {
            return false;
        }
        $stamp = date('Ymd-His');
        $ok    = true;
        if (is_file(Tenancy::dbFile($slug))) {
            $ok = @rename(Tenancy::dbFile($slug), "{$trash}/" . Tenancy::key($slug) . "-{$stamp}.sqlite") && $ok;
        }
        if (is_dir(Tenancy::dir($slug))) {
            $ok = @rename(Tenancy::dir($slug), "{$trash}/" . Tenancy::key($slug) . "-{$stamp}") && $ok;
        }

        return $ok;
    }

    private static function connect(string $slug, string $file, callable $fn): mixed
    {
        $name = 'scloud_tenant_' . $slug;
        $base = (array) config('database.connections.sqlite');
        $capsule = app('db'); // instancia de Capsule registrada por el core
        $capsule->addConnection(array_merge($base, ['driver' => 'sqlite', 'database' => $file]), $name);

        $manager = $capsule->getDatabaseManager();
        $prev    = $manager->getDefaultConnection();
        $manager->setDefaultConnection($name);
        try {
            return $fn();
        } finally {
            $manager->setDefaultConnection($prev);
            $manager->purge($name);
        }
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
