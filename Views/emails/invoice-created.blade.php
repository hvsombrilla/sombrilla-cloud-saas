@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Nueva factura {{ $invoice->code }}</h2>
<p>Hola {{ $customer->firstName() }}, generamos una factura por <strong>{{ scloud_money($invoice->total) }}</strong>@if($service) para renovar <strong>{{ $service->title() }}</strong>@endif. Vence el {{ scloud_date($invoice->due_date) }}.</p>
@include('scloud::emails._banks')
@include('scloud::emails._button', ['url' => $url, 'label' => 'Ver y pagar la factura'])
<p style="color:#8a93a6;font-size:13px">Paga a tiempo para evitar la suspensión del servicio.</p>
@endsection
