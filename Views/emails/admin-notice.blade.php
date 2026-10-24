@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">{{ $title }}</h2>
@foreach($lines as $line)<p style="margin:4px 0">{{ $line }}</p>@endforeach
@include('scloud::emails._button', ['url' => $url, 'label' => 'Abrir en el panel'])
@endsection
