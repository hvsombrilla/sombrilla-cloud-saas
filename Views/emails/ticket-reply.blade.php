@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">Respuesta a tu ticket {{ $ticket->code }}</h2>
<p><strong>{{ $ticket->subject }}</strong></p>
<div style="background:#eaf0ff;border-radius:8px;padding:14px 16px;white-space:pre-wrap">{{ $reply->message }}</div>
@include('scloud::emails._button', ['url' => $url, 'label' => 'Ver y responder'])
@endsection
