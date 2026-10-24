@extends('scloud::admin.layout')
@section('title', $server->exists ? 'Servidor ' . $server->name : 'Nuevo servidor')
@php $v = fn($k, $d = null) => scloud_old($k, $server->getAttribute($k) ?? $d); @endphp

@section('scloud')
<div class="sca-head"><h1>{{ $server->exists ? $server->name : 'Nuevo servidor WHM' }}</h1><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/scloud/servidores') }}">← Servidores</a></div>
<div class="sc-cols-21">
<form method="post" action="{{ url($server->exists ? '/scloud/servidores/' . $server->id : '/scloud/servidores') }}" class="sc-card">
  {!! scloud_csrf() !!}
  <div class="sc-grid2">
    <div class="sc-field"><label>Nombre *</label><input type="text" name="name" value="{{ $v('name') }}" required placeholder="Servidor 1"></div>
    <div class="sc-field"><label>Hostname *</label><input type="text" name="hostname" value="{{ $v('hostname') }}" required placeholder="server1.midominio.com"></div>
    <div class="sc-field"><label>Puerto WHM</label><input type="number" name="port" value="{{ $v('port', 2087) }}"></div>
    <div class="sc-field"><label>Usuario de WHM</label><input type="text" name="username" value="{{ $v('username', 'root') }}"></div>
    <div class="sc-field"><label>IP</label><input type="text" name="ip" value="{{ $v('ip') }}"></div>
    <div class="sc-field"><label>Máx. de cuentas (0 = sin límite)</label><input type="number" min="0" name="max_accounts" value="{{ $v('max_accounts', 0) }}"></div>
    <div class="sc-field"><label>Nameserver 1</label><input type="text" name="ns1" value="{{ $v('ns1') }}" placeholder="ns1.midominio.com"></div>
    <div class="sc-field"><label>Nameserver 2</label><input type="text" name="ns2" value="{{ $v('ns2') }}" placeholder="ns2.midominio.com"></div>
  </div>
  <div class="sc-field"><label>API Token de WHM {{ $server->exists && $server->hasToken() ? '(guardado; deja vacío para conservarlo)' : '*' }}</label><input type="password" name="api_token" autocomplete="new-password" placeholder="{{ $server->exists && $server->hasToken() ? '••••••••••••' : '' }}"><small>Créalo en WHM › Development › Manage API Tokens. Se guarda cifrado.</small></div>
  <div class="sc-field"><label>Notas</label><textarea name="notes" style="min-height:60px">{{ $v('notes') }}</textarea></div>
  <label class="sc-check"><input type="checkbox" name="verify_ssl" value="1" {{ scloud_old('verify_ssl', $server->exists ? $server->verify_ssl : true) ? 'checked' : '' }}> Verificar el certificado SSL (desactívalo solo si WHM usa un certificado autofirmado)</label>
  <label class="sc-check"><input type="checkbox" name="is_active" value="1" {{ scloud_old('is_active', $server->exists ? $server->is_active : true) ? 'checked' : '' }}> Activo (recibe cuentas nuevas)</label>
  <div class="sc-actions"><button class="sc-btn">Guardar</button></div>
</form>
@if($server->exists)
<div>
  <div class="sc-card"><h3>Conexión</h3><p class="sc-muted">Comprueba que el token funciona y cuántos paquetes expone WHM.</p>
    <form method="post" action="{{ url('/scloud/servidores/' . $server->id . '/probar') }}">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--ghost">Probar conexión</button></form>
    <p class="sc-muted" style="margin-bottom:0">Cuentas vivas: <strong>{{ $server->accountCount() }}</strong></p></div>
  <form method="post" action="{{ url('/scloud/servidores/' . $server->id . '/eliminar') }}" onsubmit="return confirm('¿Eliminar este servidor?')">{!! scloud_csrf() !!}<button class="sc-btn sc-btn--danger sc-btn--sm">Eliminar servidor</button></form>
</div>
@endif
</div>
@endsection
