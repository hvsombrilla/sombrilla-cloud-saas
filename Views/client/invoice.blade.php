@extends('scloud::client.layout')
@section('title', 'Factura ' . $invoice->code . ' — ' . scloud_setting('brand_name'))
@php
  [$label, $tone] = scloud_invoice_status($invoice);
  $open = in_array($invoice->status, ['draft', 'issued', 'partial', 'overdue'], true) && $invoice->balance > 0;
  $hasPending = $proofs->contains('status', 'pending');
@endphp

@section('client')
<div class="sc-page-head"><p><a href="{{ url('/cliente/facturas') }}">← Facturas</a></p>
  <div class="sc-card-head"><h1 style="font-size:1.6rem">Factura {{ $invoice->code }} {!! scloud_chip($label, $tone) !!}</h1><a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/cliente/facturas/' . $invoice->id . '/imprimir') }}" target="_blank">Imprimir / PDF</a></div></div>

<div class="sc-card">
  <dl class="sc-kv"><dt>Fecha</dt><dd>{{ scloud_date($invoice->date) }}</dd><dt>Vencimiento</dt><dd>{{ scloud_date($invoice->due_date) }}</dd>@if($invoice->ncf)<dt>NCF</dt><dd class="sc-mono">{{ $invoice->ncf }}</dd>@endif</dl>
  <div class="sc-table-wrap" style="margin-top:16px"><table class="sc-table">
    <thead><tr><th>Descripción</th><th class="sc-right">Cant.</th><th class="sc-right">Precio</th><th class="sc-right">Importe</th></tr></thead>
    <tbody>@foreach($invoice->items as $it)<tr><td>{{ $it->description }}</td><td class="sc-right">{{ rtrim(rtrim(number_format($it->quantity, 2), '0'), '.') }}</td><td class="sc-right">{{ scloud_money($it->unit_price) }}</td><td class="sc-right">{{ scloud_money($it->unit_price * $it->quantity) }}</td></tr>@endforeach</tbody>
  </table></div>
  <div style="max-width:320px;margin-left:auto;margin-top:12px">
    <div class="sc-line"><span>Subtotal</span><span>{{ scloud_money($invoice->subtotal) }}</span></div>
    <div class="sc-line"><span>{{ scloud_setting('tax_label') }}</span><span>{{ scloud_money($invoice->itbis) }}</span></div>
    <div class="sc-total"><span>Total</span><span>{{ scloud_money($invoice->total) }}</span></div>
    @if($invoice->paid > 0)<div class="sc-line"><span>Pagado</span><span>{{ scloud_money($invoice->paid) }}</span></div><div class="sc-line"><strong>Saldo</strong><strong>{{ scloud_money($invoice->balance) }}</strong></div>@endif
  </div>
</div>

@if($open)
<div class="sc-card">
  <h3>Pagar por transferencia</h3>
  <p class="sc-muted">{{ scloud_setting('transfer_instructions') }} Monto a transferir: <strong>{{ scloud_money($invoice->balance) }}</strong>.</p>
  @foreach($banks as $bank)
    <div class="sc-bank"><strong>{{ $bank->bank }}</strong><dl><dt>Tipo</dt><dd>{{ $bank->account_type }} ({{ $bank->currency }})</dd><dt>Cuenta</dt><dd class="sc-mono">{{ $bank->account_number }}</dd><dt>Titular</dt><dd>{{ $bank->holder }}@if($bank->holder_id) · {{ $bank->holder_id }}@endif</dd></dl>@if($bank->instructions)<small class="sc-muted">{{ $bank->instructions }}</small>@endif</div>
  @endforeach
</div>

@if($hasPending)
  <div class="sc-alert sc-alert--info">Tu comprobante está en revisión. Te avisaremos por correo cuando lo validemos.</div>
@else
<div class="sc-card">
  <h3>Subir comprobante</h3>
  <form method="post" action="{{ url('/cliente/facturas/' . $invoice->id . '/comprobante') }}" enctype="multipart/form-data">
    {!! scloud_csrf() !!}
    <div class="sc-grid2">
      <div class="sc-field"><label>Banco destino</label><select name="bank_account_id"><option value="">—</option>@foreach($banks as $b)<option value="{{ $b->id }}" {{ (int) scloud_old('bank_account_id') === $b->id ? 'selected' : '' }}>{{ $b->bank }} · {{ $b->account_number }}</option>@endforeach</select></div>
      <div class="sc-field"><label>Monto transferido *</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ scloud_old('amount', number_format($invoice->balance, 2, '.', '')) }}" required></div>
      <div class="sc-field"><label>Fecha de la transferencia *</label><input type="date" name="paid_at" max="{{ date('Y-m-d') }}" value="{{ scloud_old('paid_at', date('Y-m-d')) }}" required></div>
      <div class="sc-field"><label>No. de referencia *</label><input type="text" name="reference" value="{{ scloud_old('reference') }}" required maxlength="100"></div>
    </div>
    <div class="sc-field"><label>Comprobante (imagen o PDF, máx. 8 MB) *</label><input type="file" name="proof" accept="image/*,.pdf" required></div>
    <div class="sc-field"><label>Notas (opcional)</label><input type="text" name="notes" value="{{ scloud_old('notes') }}" maxlength="1000"></div>
    <button class="sc-btn">Enviar comprobante</button>
  </form>
</div>
@endif
@endif

@if($proofs->count())
<div class="sc-card">
  <h3>Comprobantes enviados</h3>
  <div class="sc-table-wrap"><table class="sc-table"><thead><tr><th>Fecha</th><th>Monto</th><th>Referencia</th><th>Estado</th><th></th></tr></thead><tbody>
    @foreach($proofs as $p)
      <tr><td>{{ scloud_date($p->paid_at) }}</td><td>{{ scloud_money($p->amount) }}</td><td class="sc-mono">{{ $p->reference }}</td>
        <td>{!! scloud_chip($p->statusLabel(), $p->statusTone()) !!}@if($p->status === 'rejected' && $p->review_notes)<div class="sc-muted" style="font-size:.85rem">{{ $p->review_notes }}</div>@endif</td>
        <td class="sc-right"><a href="{{ url('/cliente/comprobantes/' . $p->id) }}" target="_blank">Ver archivo</a></td></tr>
    @endforeach
  </tbody></table></div>
</div>
@endif
@endsection
