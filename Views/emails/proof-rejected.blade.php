@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">No pudimos validar tu comprobante</h2>
<p>Hola {{ $customer->firstName() }}, el comprobante de la factura <strong>{{ $invoice->code }}</strong> no fue aceptado.</p>
@if($proof->review_notes)<p style="background:#fde8eb;border-radius:8px;padding:12px 14px"><strong>Motivo:</strong> {{ $proof->review_notes }}</p>@endif
<p>Puedes subir un comprobante nuevo desde tu panel.</p>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Subir otro comprobante'])
@endsection
