@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Tu hosting está listo 🎉</h2>
<p>Hola {{ $customer->firstName() }}, ya creamos la cuenta de <strong>{{ $service->domain }}</strong>.</p>
<table role="presentation" cellpadding="6" style="background:#f8f9fc;border:1px solid #e3e8f1;border-radius:8px;font-size:14px;width:100%">
  <tr><td width="140" style="color:#8a93a6">Usuario cPanel</td><td><strong>{{ $service->username }}</strong></td></tr>
  <tr><td style="color:#8a93a6">Contraseña</td><td><strong style="font-family:monospace">{{ $service->plainPassword() }}</strong></td></tr>
  @if($service->server)<tr><td style="color:#8a93a6">Panel cPanel</td><td><a href="{{ $service->server->cpanelUrl() }}">{{ $service->server->cpanelUrl() }}</a></td></tr>
  @if($service->server->ns1)<tr><td style="color:#8a93a6">Nameservers</td><td>{{ $service->server->ns1 }}@if($service->server->ns2)<br>{{ $service->server->ns2 }}@endif</td></tr>@endif
  @if($service->server->ip)<tr><td style="color:#8a93a6">IP del servidor</td><td>{{ $service->server->ip }}</td></tr>@endif @endif
</table>
<p style="color:#5b6578;font-size:13px">Cambia la contraseña desde tu panel de cliente. Para que tu dominio apunte al servicio, configura los nameservers indicados en tu registrador.</p>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Administrar mi servicio'])
@endsection
