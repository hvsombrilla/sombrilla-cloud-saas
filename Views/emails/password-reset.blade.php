@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Restablecer contraseña</h2>
<p>Hola {{ $customer->firstName() }}, recibimos una solicitud para cambiar tu contraseña. El enlace vence en 1 hora.</p>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Crear contraseña nueva'])
<p style="color:#8a93a6;font-size:13px">Si no lo solicitaste, ignora este mensaje: tu contraseña no cambiará.</p>
@endsection
