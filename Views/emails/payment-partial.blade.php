@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Abono recibido</h2>
<p>Hola {{ $customer->firstName() }}, registramos tu transferencia. La factura <strong>{{ $invoice->code }}</strong> aún tiene un saldo de <strong>{{ scloud_money($invoice->balance) }}</strong>. Tu servicio se activará al completar el pago.</p>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Ver factura'])
@endsection
