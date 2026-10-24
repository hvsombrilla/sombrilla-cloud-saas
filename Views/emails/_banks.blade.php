@if(isset($banks) && count($banks))
<p style="margin:18px 0 6px"><strong>Datos para la transferencia</strong></p>
@foreach($banks as $b)
<table role="presentation" width="100%" cellpadding="6" style="background:#f8f9fc;border:1px solid #e3e8f1;border-radius:8px;margin-bottom:8px;font-size:14px">
  <tr><td colspan="2"><strong>{{ $b->bank }}</strong></td></tr>
  <tr><td width="90" style="color:#8a93a6">Cuenta</td><td>{{ $b->account_type }} ({{ $b->currency }}) · <strong>{{ $b->account_number }}</strong></td></tr>
  <tr><td style="color:#8a93a6">Titular</td><td>{{ $b->holder }}@if($b->holder_id) · {{ $b->holder_id }}@endif</td></tr>
</table>
@endforeach
<p style="color:#5b6578;font-size:13px">{{ scloud_setting('transfer_instructions') }}</p>
@endif
