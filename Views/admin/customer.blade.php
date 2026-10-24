@extends('scloud::admin.layout')
@section('title', $customer->name)

@section('scloud')
<div class="sca-head"><h1>{{ $customer->name }} {!! scloud_chip($customer->status === 'active' ? 'Activo' : 'Deshabilitado', $customer->status === 'active' ? 'ok' : 'bad') !!}</h1>
  @if($customer->contact_id)<a class="sc-btn sc-btn--ghost sc-btn--sm" href="{{ url('/erp/contacts/' . $customer->contact_id) }}">Contacto en el ERP ↗</a>@endif</div>
<div class="sc-cols-21">
<div>
  <div class="sc-card"><h3>Servicios</h3><div class="sc-table-wrap"><table class="sc-table"><tbody>
    @forelse($services as $s)<tr><td><a href="{{ url('/scloud/servicios/' . $s->id) }}">{{ $s->title() }}</a></td><td>{{ $s->cycleLabel() }}</td><td>{{ scloud_money($s->amount) }}</td><td>{{ scloud_date($s->next_due_date) }}</td><td>{!! scloud_chip($s->statusLabel(), $s->statusTone()) !!}</td></tr>@empty<tr><td class="sc-empty">Sin servicios.</td></tr>@endforelse
  </tbody></table></div></div>
  <div class="sc-cols">
    <div class="sc-card"><h3>Pedidos</h3>@forelse($orders as $o)<div class="sc-line"><a href="{{ url('/scloud/pedidos/' . $o->id) }}">{{ $o->code }}</a><span>{{ scloud_money($o->total) }} {!! scloud_chip($o->statusLabel(), $o->statusTone()) !!}</span></div>@empty<div class="sc-muted">Sin pedidos.</div>@endforelse</div>
    <div class="sc-card"><h3>Facturas</h3>@forelse($invoices as $i)<div class="sc-line"><a href="{{ url('/erp/invoices/' . $i->id) }}">{{ $i->code }}</a><span>{{ scloud_money($i->total) }} · saldo {{ scloud_money($i->balance) }}</span></div>@empty<div class="sc-muted">Sin facturas.</div>@endforelse</div>
  </div>
  <div class="sc-card"><h3>Tickets</h3>@forelse($tickets as $t)<div class="sc-line"><a href="{{ url('/scloud/tickets/' . $t->id) }}">[{{ $t->code }}] {{ $t->subject }}</a>{!! scloud_chip($t->statusLabel(), $t->statusTone()) !!}</div>@empty<div class="sc-muted">Sin tickets.</div>@endforelse</div>
</div>
<div class="sc-card"><h3>Datos del cliente</h3>
  <form method="post" action="{{ url('/scloud/clientes/' . $customer->id) }}">
    {!! scloud_csrf() !!}
    <div class="sc-field"><label>Nombre</label><input type="text" name="name" value="{{ $customer->name }}" required></div>
    <div class="sc-field"><label>Empresa</label><input type="text" name="company" value="{{ $customer->company }}"></div>
    <div class="sc-field"><label>Correo</label><input type="email" name="email" value="{{ $customer->email }}" required></div>
    <div class="sc-field"><label>Teléfono</label><input type="text" name="phone" value="{{ $customer->phone }}"></div>
    <div class="sc-field"><label>RNC / Cédula</label><input type="text" name="tax_id" value="{{ $customer->tax_id }}"></div>
    <div class="sc-field"><label>Dirección</label><input type="text" name="address" value="{{ $customer->address }}"></div>
    <div class="sc-grid2"><div class="sc-field"><label>Ciudad</label><input type="text" name="city" value="{{ $customer->city }}"></div><div class="sc-field"><label>País</label><input type="text" name="country" value="{{ $customer->country }}"></div></div>
    <div class="sc-field"><label>Estado de la cuenta</label><select name="status"><option value="active" {{ $customer->status === 'active' ? 'selected' : '' }}>Activo</option><option value="disabled" {{ $customer->status === 'disabled' ? 'selected' : '' }}>Deshabilitado (no puede ingresar)</option></select></div>
    <div class="sc-field"><label>Nueva contraseña</label><input type="password" name="new_password" autocomplete="new-password" placeholder="Dejar vacío para no cambiar"></div>
    <div class="sc-field"><label>Notas internas</label><textarea name="notes">{{ $customer->notes }}</textarea></div>
    <button class="sc-btn">Guardar</button>
    <p class="sc-muted" style="margin:12px 0 0;font-size:12px">Último acceso: {{ $customer->last_login_at ? $customer->last_login_at->format('d/m/Y H:i') : 'nunca' }} {{ $customer->last_login_ip }}</p>
  </form>
</div>
</div>
@endsection
