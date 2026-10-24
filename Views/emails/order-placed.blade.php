@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Recibimos tu pedido {{ $order->code }}</h2>
<p>Hola {{ $customer->firstName() }}, gracias por elegirnos. Para activar tu servicio, transfiere <strong>{{ scloud_money($invoice->total) }}</strong> (factura {{ $invoice->code }}) y sube el comprobante desde tu panel.</p>
<ul style="padding-left:20px">@foreach($order->services as $s)<li>{{ $s->plan->name ?? 'Plan' }}@if($s->host()) — {{ $s->host() }}@endif · {{ $s->cycleLabel() }}</li>@endforeach</ul>
@include('scloud::emails._banks')
@include('scloud::emails._button', ['url' => $url, 'label' => 'Subir comprobante'])
@endsection
