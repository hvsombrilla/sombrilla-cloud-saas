@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Servicio suspendido</h2>
<p>Hola {{ $customer->firstName() }}, suspendimos <strong>{{ $service->host() ?: $service->title() }}</strong>@if($service->suspend_reason) por: {{ $service->suspend_reason }}@endif.</p>
<p>Para reactivarlo, paga la factura pendiente y sube tu comprobante. Lo reactivamos apenas se valide el pago.</p>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Ver mis facturas'])
@endsection
