<?php

namespace SombrillaCloud\Controllers\Admin;

use Illuminate\Http\Request;
use SombrillaCloud\Models\Plan;
use SombrillaCloud\Models\Server;
use SombrillaCloud\Models\Service;
use SombrillaCloud\Services\Whm;
use SombrillaCloud\Services\WhmException;

class ServerController
{
    public function index()
    {
        return scloud_view('admin.servers', ['servers' => Server::orderBy('name')->get()]);
    }

    public function create()
    {
        return scloud_view('admin.server-form', ['server' => new Server(['port' => 2087, 'username' => 'root', 'verify_ssl' => true, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $server = new Server();
        if ($error = $this->fill($server, $request)) {
            return scloud_back_with('/scloud/servidores/nuevo', [$error], $request->all());
        }
        flash('success', 'Servidor agregado. Prueba la conexión para verificar el API Token.');

        return redirect(url('/scloud/servidores/' . $server->id));
    }

    public function edit(int $id)
    {
        return scloud_view('admin.server-form', ['server' => Server::findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $server = Server::findOrFail($id);
        if ($error = $this->fill($server, $request)) {
            return scloud_back_with('/scloud/servidores/' . $id, [$error], $request->all());
        }
        flash('success', 'Servidor guardado.');

        return redirect(url('/scloud/servidores/' . $id));
    }

    public function test(int $id)
    {
        $server = Server::findOrFail($id);
        try {
            $packages = count(Whm::for($server)->packages());
            flash('success', "Conexión correcta con {$server->hostname} · {$packages} paquete(s) disponibles en WHM.");
        } catch (WhmException $e) {
            flash('error', $e->getMessage());
        }

        return redirect(url('/scloud/servidores/' . $id));
    }

    public function destroy(int $id)
    {
        $server = Server::findOrFail($id);
        if (Service::where('server_id', $id)->exists()) {
            flash('error', 'El servidor tiene servicios asociados. Desactívalo en lugar de eliminarlo.');

            return redirect(url('/scloud/servidores/' . $id));
        }
        Plan::where('server_id', $id)->update(['server_id' => null]);
        $server->delete();
        flash('success', 'Servidor eliminado.');

        return redirect(url('/scloud/servidores'));
    }

    /** JSON con los paquetes de WHM (para el selector del formulario de planes). */
    public function packages(int $id)
    {
        try {
            return response()->json(['ok' => true, 'packages' => Whm::for(Server::findOrFail($id))->packages()]);
        } catch (WhmException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function fill(Server $server, Request $request): ?string
    {
        $name = trim((string) $request->input('name'));
        $host = strtolower(trim((string) $request->input('hostname')));
        if ($name === '' || !preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host)) {
            return 'Indica el nombre y un hostname válido (por ejemplo: server1.midominio.com).';
        }
        $server->fill([
            'name' => mb_substr($name, 0, 150), 'hostname' => $host, 'ip' => mb_substr(trim((string) $request->input('ip')), 0, 45) ?: null,
            'port' => (int) $request->input('port') ?: 2087, 'username' => mb_substr(trim((string) $request->input('username')) ?: 'root', 0, 60),
            'verify_ssl' => (bool) $request->input('verify_ssl'), 'is_active' => (bool) $request->input('is_active'),
            'ns1' => trim((string) $request->input('ns1')) ?: null, 'ns2' => trim((string) $request->input('ns2')) ?: null,
            'max_accounts' => max(0, (int) $request->input('max_accounts')), 'notes' => trim((string) $request->input('notes')) ?: null,
        ]);
        // Token vacío = conservar el actual.
        if (($token = trim((string) $request->input('api_token'))) !== '') {
            $server->setToken($token);
        }
        $server->save();

        return null;
    }
}
