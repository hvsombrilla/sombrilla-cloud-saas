@extends('scloud::client.layout')
@section('title', 'Facturas — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><h1 style="font-size:1.6rem">Facturas y pagos</h1></div>
<div class="sc-card"><div class="sc-table-wrap">
  <table class="sc-table">
    <thead><tr><th>Factura</th><th>Fecha</th><th>Vence</th><th>Total</th><th>Saldo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      @forelse($invoices as $inv) @php [$label, $tone] = scloud_invoice_status($inv); @endphp
        <tr>
          <td><a href="{{ url('/cliente/facturas/' . $inv->id) }}"><strong>{{ $inv->code }}</strong></a></td>
          <td>{{ scloud_date($inv->date) }}</td><td>{{ scloud_date($inv->due_date) }}</td>
          <td>{{ scloud_money($inv->total) }}</td><td>{{ scloud_money($inv->balance) }}</td>
          <td>{!! scloud_chip($label, $tone) !!}</td>
          <td class="sc-right"><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/cliente/facturas/' . $inv->id) }}">{{ $inv->balance > 0 ? 'Pagar' : 'Ver' }}</a></td>
        </tr>
      @empty
        <tr><td colspan="7" class="sc-empty">No tienes facturas todavía.</td></tr>
      @endforelse
    </tbody>
  </table>
</div></div>
@endsection
