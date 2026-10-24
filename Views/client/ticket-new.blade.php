@extends('scloud::client.layout')
@section('title', 'Nuevo ticket — ' . scloud_setting('brand_name'))

@section('client')
<div class="sc-page-head"><p><a href="{{ url('/cliente/tickets') }}">← Soporte</a></p><h1 style="font-size:1.6rem">Nuevo ticket</h1></div>
<div class="sc-card">
  <form method="post" action="{{ url('/cliente/tickets') }}" enctype="multipart/form-data">
    {!! scloud_csrf() !!}
    <div class="sc-grid2">
      <div class="sc-field"><label>Departamento</label><select name="department">@foreach(\SombrillaCloud\Models\Ticket::DEPARTMENTS as $k => $v)<option value="{{ $k }}" {{ scloud_old('department') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
      <div class="sc-field"><label>Prioridad</label><select name="priority">@foreach(\SombrillaCloud\Models\Ticket::PRIORITIES as $k => [$v])<option value="{{ $k }}" {{ scloud_old('priority', 'medium') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
    </div>
    <div class="sc-field"><label>Servicio relacionado</label><select name="service_id"><option value="">Ninguno</option>@foreach($services as $s)<option value="{{ $s->id }}" {{ (int) scloud_old('service_id', $selected) === $s->id ? 'selected' : '' }}>{{ $s->title() }}</option>@endforeach</select></div>
    <div class="sc-field"><label>Asunto *</label><input type="text" name="subject" value="{{ scloud_old('subject') }}" required maxlength="200"></div>
    <div class="sc-field"><label>Mensaje *</label><textarea name="message" required>{{ scloud_old('message') }}</textarea></div>
    <div class="sc-field"><label>Adjunto (opcional, máx. 8 MB)</label><input type="file" name="attachment"></div>
    <button class="sc-btn">Enviar ticket</button>
  </form>
</div>
@endsection
