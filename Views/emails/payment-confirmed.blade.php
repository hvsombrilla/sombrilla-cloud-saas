@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Pago recibido ✔</h2>
<p>Hola {{ $customer->firstName() }}, confirmamos el pago de la factura <strong>{{ $invoice->code }}</strong> por {{ scloud_money($invoice->total) }}. ¡Gracias!</p>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Ver factura'])
@endsection
