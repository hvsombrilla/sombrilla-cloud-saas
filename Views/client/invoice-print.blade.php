@php $brand = scloud_setting('brand_name'); [$label] = scloud_invoice_status($invoice); @endphp
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Factura {{ $invoice->code }} — {{ $brand }}</title>
<link rel="stylesheet" href="{{ scloud_asset('site.css') }}">
<style>body.sc{background:#fff} .doc{max-width:820px;margin:0 auto;padding:40px 24px} .hd{display:flex;justify-content:space-between;gap:20px;margin-bottom:30px}</style>
</head>
<body class="sc"><div class="doc">
  <p class="no-print" style="text-align:right"><button class="sc-btn" onclick="window.print()">Imprimir / guardar PDF</button></p>
  <div class="hd">
    <div><h2 style="margin:0">{{ $brand }}</h2><div class="sc-muted">{{ scloud_setting('address') }}<br>{{ scloud_setting('support_email') }} {{ scloud_setting('support_phone') }}</div></div>
    <div class="sc-right"><h2 style="margin:0">Factura {{ $invoice->code }}</h2>@if($invoice->ncf)<div class="sc-mono">NCF {{ $invoice->ncf }}</div>@endif<div>{{ $label }}</div></div>
  </div>
  <div class="hd">
    <div><strong>Facturar a</strong><br>{{ $invoice->client_name ?: ($customer->company ?: $customer->name) }}<br>@if($invoice->client_document)<span class="sc-muted">RNC/Cédula: {{ $invoice->client_document }}</span><br>@endif<span class="sc-muted">{{ $customer->email }}</span></div>
    <div class="sc-right"><strong>Fecha:</strong> {{ scloud_date($invoice->date) }}<br><strong>Vence:</strong> {{ scloud_date($invoice->due_date) }}</div>
  </div>
  <table class="sc-table"><thead><tr><th>Descripción</th><th class="sc-right">Cant.</th><th class="sc-right">Precio</th><th class="sc-right">Importe</th></tr></thead><tbody>
    @foreach($invoice->items as $it)<tr><td>{{ $it->description }}</td><td class="sc-right">{{ rtrim(rtrim(number_format($it->quantity, 2), '0'), '.') }}</td><td class="sc-right">{{ scloud_money($it->unit_price) }}</td><td class="sc-right">{{ scloud_money($it->unit_price * $it->quantity) }}</td></tr>@endforeach
  </tbody></table>
  <div style="max-width:300px;margin:16px 0 0 auto">
    <div class="sc-line"><span>Subtotal</span><span>{{ scloud_money($invoice->subtotal) }}</span></div>
    <div class="sc-line"><span>{{ scloud_setting('tax_label') }}</span><span>{{ scloud_money($invoice->itbis) }}</span></div>
    <div class="sc-total"><span>Total</span><span>{{ scloud_money($invoice->total) }}</span></div>
    <div class="sc-line"><span>Pagado</span><span>{{ scloud_money($invoice->paid) }}</span></div><div class="sc-line"><strong>Saldo</strong><strong>{{ scloud_money($invoice->balance) }}</strong></div>
  </div>
  @if($invoice->notes)<p class="sc-muted" style="margin-top:24px">{{ $invoice->notes }}</p>@endif
</div></body></html>
