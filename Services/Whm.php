<?php

namespace SombrillaCloud\Services;

use SombrillaCloud\Models\Server;

/**
 * Cliente mínimo de la WHM API 1 (json-api) autenticado con API Token:
 *   Authorization: whm {usuario}:{token}
 * Documentación: https://api.docs.cpanel.net/whm/introduction
 */
class Whm
{
    public function __construct(private Server $server)
    {
    }

    public static function for(Server $server): self
    {
        return new self($server);
    }

    /**
     * Llama a una función de la API y devuelve `data`. Lanza WhmException si la API falla.
     */
    public function call(string $function, array $params = [], int $timeout = 60): array
    {
        $token = $this->server->token();
        if ($token === '') {
            throw new WhmException('El servidor «' . $this->server->name . '» no tiene API Token configurado.');
        }

        $params = ['api.version' => 1] + $params;
        $url    = $this->server->whmUrl() . 'json-api/' . rawurlencode($function) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: whm ' . ($this->server->username ?: 'root') . ':' . $token],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => (bool) $this->server->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $this->server->verify_ssl ? 2 : 0,
            CURLOPT_USERAGENT      => 'SombrillaCloud/' . SCLOUD_VERSION,
        ]);
        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new WhmException('No se pudo conectar con WHM (' . $this->server->hostname . '): ' . $error);
        }
        if ($code === 401 || $code === 403) {
            throw new WhmException('WHM rechazó las credenciales (HTTP ' . $code . '). Revisa el usuario y el API Token.');
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            throw new WhmException('Respuesta inválida de WHM (HTTP ' . $code . ').');
        }

        $meta = $json['metadata'] ?? [];
        if ((int) ($meta['result'] ?? 0) !== 1) {
            throw new WhmException(trim((string) ($meta['reason'] ?? 'Error desconocido de WHM.')));
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    /** Prueba la conexión: devuelve la versión de WHM. */
    public function version(): string
    {
        return (string) ($this->call('version', [], 20)['version'] ?? '');
    }

    /** Paquetes (planes) de WHM: lista de nombres con sus límites principales. */
    public function packages(): array
    {
        $out = [];
        foreach ($this->call('listpkgs', ['want' => 'all'])['pkg'] ?? [] as $pkg) {
            $out[] = [
                'name'      => (string) ($pkg['name'] ?? ''),
                'quota'     => $pkg['QUOTA'] ?? null,
                'bandwidth' => $pkg['BWLIMIT'] ?? null,
                'emails'    => $pkg['MAXPOP'] ?? null,
                'databases' => $pkg['MAXSQL'] ?? null,
                'addons'    => $pkg['MAXADDON'] ?? null,
            ];
        }

        return $out;
    }

    public function accountExists(string $username): bool
    {
        try {
            $this->call('accountsummary', ['user' => $username], 20);

            return true;
        } catch (WhmException) {
            return false;
        }
    }

    public function createAccount(string $username, string $domain, string $password, string $package, string $email): array
    {
        return $this->call('createacct', [
            'username'     => $username,
            'domain'       => $domain,
            'password'     => $password,
            'plan'         => $package,
            'contactemail' => $email,
        ], 180);
    }

    public function suspend(string $username, string $reason = ''): void
    {
        $this->call('suspendacct', ['user' => $username, 'reason' => $reason ?: 'Suspendida por Sombrilla Cloud']);
    }

    public function unsuspend(string $username): void
    {
        $this->call('unsuspendacct', ['user' => $username]);
    }

    public function terminate(string $username): void
    {
        $this->call('removeacct', ['username' => $username], 180);
    }

    public function changePackage(string $username, string $package): void
    {
        $this->call('changepackage', ['user' => $username, 'pkg' => $package]);
    }

    public function changePassword(string $username, string $password): void
    {
        $this->call('passwd', ['user' => $username, 'password' => $password]);
    }

    /** URL de acceso directo (SSO) a cPanel, válida por unos minutos. */
    public function cpanelSession(string $username): string
    {
        $data = $this->call('create_user_session', ['user' => $username, 'service' => 'cpaneld'], 30);
        $url  = (string) ($data['url'] ?? '');
        if ($url === '') {
            throw new WhmException('WHM no devolvió la URL de acceso.');
        }

        return $url;
    }

    /** Uso de disco/ancho de banda y estado, para mostrar en el panel. */
    public function summary(string $username): array
    {
        $acct = $this->call('accountsummary', ['user' => $username], 20)['acct'][0] ?? [];

        return [
            'disk_used'  => $acct['diskused'] ?? null,
            'disk_limit' => $acct['disklimit'] ?? null,
            'suspended'  => !empty($acct['suspended']),
            'ip'         => $acct['ip'] ?? null,
            'package'    => $acct['plan'] ?? null,
        ];
    }
}
