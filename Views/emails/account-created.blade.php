@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">{{ $reset ? 'Nuevos accesos a tu instancia' : 'Tu instancia está lista 🎉' }}</h2>
<p>Hola {{ $customer->firstName() }}, {{ $reset ? 'generamos una contraseña nueva para' : 'ya creamos' }} <strong>{{ $service->workspace_name }}</strong>.</p>
<table role="presentation" cellpadding="6" style="background:#f8f9fc;border:1px solid #e3e8f1;border-radius:8px;font-size:14px;width:100%">
  <tr><td width="150" style="color:#8a93a6">Workspace</td><td><strong>{{ $service->workspace_name }}</strong></td></tr>
  <tr><td style="color:#8a93a6">Dirección</td><td><a href="{{ $service->publicUrl() }}">{{ $service->publicUrl() }}</a></td></tr>
  <tr><td style="color:#8a93a6">Usuario (correo)</td><td><strong>{{ $service->admin_email }}</strong></td></tr>
  <tr><td style="color:#8a93a6">Contraseña</td><td><strong style="font-family:monospace">{{ $password }}</strong></td></tr>
</table>
<p style="color:#5b6578;font-size:13px">Guarda esta contraseña y cámbiala al entrar. También puedes abrir tu instancia sin escribirla desde tu panel de cliente con el botón «Abrir mi instancia».</p>

@if($service->domain_mode === 'buy' && $service->domain)
<h3 style="margin-bottom:6px">Tu dominio {{ $service->domain }}</h3>
<p style="font-size:14px">{{ $service->domain_status === 'failed' ? 'No pudimos completar el registro del dominio; nuestro equipo ya fue avisado y te contactará. Mientras tanto usa la dirección de arriba.' : 'Lo registramos a tu nombre y lo apuntamos a tu instancia. Puede tardar unos minutos en activarse (incluido el certificado SSL).' }}</p>
@endif

@if($dns)
<h3 style="margin-bottom:6px">Conecta tu dominio {{ $service->domain }}</h3>
<p style="font-size:14px">Entra al panel DNS de tu dominio (donde lo compraste) y crea estos registros:</p>
<table role="presentation" cellpadding="6" style="background:#f8f9fc;border:1px solid #e3e8f1;border-radius:8px;font-size:13px;width:100%">
  <tr style="color:#8a93a6"><td>Tipo</td><td>Nombre</td><td>Valor</td></tr>
  @foreach($dns['records'] as $r)<tr><td><strong>{{ $r['type'] }}</strong></td><td style="font-family:monospace">{{ $r['name'] }}</td><td style="font-family:monospace">{{ $r['value'] }}</td></tr>@endforeach
</table>
<p style="color:#5b6578;font-size:13px">Los cambios de DNS pueden tardar desde unos minutos hasta unas horas. Cuando estén listos, pulsa «Verificar» en tu panel de cliente. Mientras tanto tu instancia funciona en {{ $service->host() }}.</p>
@endif

@include('scloud::emails._button', ['url' => $url, 'label' => 'Ir a mi panel de cliente'])
@endsection
