@extends('scloud::emails.layout')
@section('body')
<h2 style="margin-top:0">¡Bienvenido, {{ $customer->firstName() }}!</h2>
<p>Tu cuenta en {{ $brand }} está lista. Desde tu panel puedes contratar planes, pagar facturas, administrar tus servicios y abrir tickets de soporte.</p>
@include('scloud::emails._button', ['url' => url('/cliente'), 'label' => 'Ir a mi panel'])
@endsection
