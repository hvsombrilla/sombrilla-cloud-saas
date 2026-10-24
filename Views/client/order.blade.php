@extends('scloud::client.layout')
@section('title', 'Pedido ' . $order->code . ' — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><h1 style="font-size:1.6rem">Pedido {{ $order->code }} {!! scloud_chip($order->statusLabel(), $order->statusTone()) !!}</h1><p>Realizado el {{ scloud_date($order->created_at) }}</p></div>

@if($order->status === 'pending' && $invoice)
  <div class="sc-alert sc-alert--info"><strong>Siguiente paso:</strong> realiza la transferencia por <strong>{{ scloud_money($invoice->total) }}</strong> y sube el comprobante en la factura {{ $invoice->code }}.
    <br><a class="sc-btn sc-btn--sm" style="margin-top:10px" href="{{ url('/cliente/facturas/' . $invoice->id) }}">Subir comprobante</a></div>
  <div class="sc-card">
    <h3>Datos para la transferencia</h3>
    <p class="sc-muted">{{ scloud_setting('transfer_instructions') }}</p>
    @foreach($banks as $bank)
      <div class="sc-bank"><strong>{{ $bank->bank }}</strong><dl><dt>Tipo</dt><dd>{{ $bank->account_type }} ({{ $bank->currency }})</dd><dt>Cuenta</dt><dd class="sc-mono">{{ $bank->account_number }}</dd><dt>Titular</dt><dd>{{ $bank->holder }}@if($bank->holder_id) · {{ $bank->holder_id }}@endif</dd></dl>@if($bank->instructions)<small class="sc-muted">{{ $bank->instructions }}</small>@endif</div>
    @endforeach
  </div>
@endif

<div class="sc-card">
  <h3>Servicios del pedido</h3>
  @foreach($order->services as $s)
    <div class="sc-line" style="align-items:center"><div><a href="{{ url('/cliente/servicios/' . $s->id) }}"><strong>{{ $s->title() }}</strong></a><div class="sc-muted" style="font-size:.85rem">{{ $s->cycleLabel() }} · {{ scloud_money($s->amount) }}</div></div>{!! scloud_chip($s->statusLabel(), $s->statusTone()) !!}</div>
  @endforeach
  <div class="sc-total"><span>Total (con impuestos)</span><span>{{ scloud_money($order->total) }}</span></div>
</div>
@endsection
