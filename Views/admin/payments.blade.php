@extends('scloud::admin.layout')
@section('title', 'Pagos por validar — Sombrilla Cloud')

@section('scloud')
<div class="sca-head"><h1>Pagos por transferencia</h1></div>
<div class="sc-filters">
  @foreach(['pending' => 'Por validar', 'approved' => 'Aprobados', 'rejected' => 'Rechazados', 'all' => 'Todos'] as $k => $l)
    <a class="sc-btn sc-btn--sm {{ $status === $k ? '' : 'sc-btn--ghost' }}" href="{{ url('/scloud/pagos?estado=' . $k) }}">{{ $l }}</a>
  @endforeach
</div>

@forelse($proofs as $p)
@php $ext = strtolower(pathinfo((string) $p->file, PATHINFO_EXTENSION)); @endphp
<div class="sc-card">
  <div class="sc-card-head"><h3>{{ $p->customer->name ?? '—' }} · {{ scloud_money($p->amount) }}</h3>{!! scloud_chip($p->statusLabel(), $p->statusTone()) !!}</div>
  <div class="sc-cols">
    <dl class="sc-kv">
      <dt>Factura</dt><dd>@if($p->invoice)<a href="{{ url('/erp/invoices/' . $p->invoice_id) }}">{{ $p->invoice->code }}</a> · total {{ scloud_money($p->invoice->total) }} · saldo {{ scloud_money($p->invoice->balance) }}@else — @endif</dd>
      <dt>Fecha transferencia</dt><dd>{{ scloud_date($p->paid_at) }}</dd><dt>Referencia</dt><dd class="sc-mono">{{ $p->reference }}</dd>
      <dt>Banco destino</dt><dd>{{ $p->bankAccount->bank ?? '—' }}</dd><dt>Enviado</dt><dd>{{ $p->created_at->format('d/m/Y H:i') }}</dd>
      @if($p->notes)<dt>Notas</dt><dd>{{ $p->notes }}</dd>@endif
      @if($p->status !== 'pending')<dt>Revisión</dt><dd>{{ $p->reviewed_at ? $p->reviewed_at->format('d/m/Y H:i') : '' }} {{ $p->review_notes }}</dd>@endif
    </dl>
    <div>
      @if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true))<a href="{{ url('/scloud/pagos/' . $p->id . '/archivo') }}" target="_blank"><img class="sc-proof-img" src="{{ url('/scloud/pagos/' . $p->id . '/archivo') }}" alt="Comprobante"></a>
      @else<a class="sc-btn sc-btn--ghost" href="{{ url('/scloud/pagos/' . $p->id . '/archivo') }}" target="_blank">📄 Abrir comprobante ({{ strtoupper($ext) }})</a>@endif
    </div>
  </div>
  @if($p->status === 'pending')
  <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">
  <div class="sc-cols">
    <form method="post" action="{{ url('/scloud/pagos/' . $p->id . '/aprobar') }}" onsubmit="return confirm('¿Aprobar y registrar el pago en el ERP? Se activará el servicio si la factura queda saldada.')">
      {!! scloud_csrf() !!}
      <div class="sc-grid2"><div class="sc-field"><label>Monto a registrar</label><input type="number" step="0.01" name="amount" value="{{ number_format($p->amount, 2, '.', '') }}"></div><div class="sc-field"><label>Nota interna</label><input type="text" name="notes"></div></div>
      <button class="sc-btn sc-btn--ok">✔ Aprobar y registrar pago</button>
    </form>
    <form method="post" action="{{ url('/scloud/pagos/' . $p->id . '/rechazar') }}">
      {!! scloud_csrf() !!}
      <div class="sc-field"><label>Motivo del rechazo (lo verá el cliente)</label><input type="text" name="reason" required></div>
      <button class="sc-btn sc-btn--danger">✕ Rechazar</button>
    </form>
  </div>
  @endif
</div>
@empty
<div class="sc-card sc-empty">No hay comprobantes en esta vista.</div>
@endforelse
{!! scloud_pager($proofs) !!}
@endsection
