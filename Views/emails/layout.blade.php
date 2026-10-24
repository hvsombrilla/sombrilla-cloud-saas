<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subject ?? $brand }}</title></head>
<body style="margin:0;padding:0;background:#f3f5fa;font-family:Arial,Helvetica,sans-serif;color:#172033">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5fa;padding:28px 12px"><tr><td align="center">
  <table role="presentation" width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e3e8f1">
    <tr><td style="background:#0c1633;color:#fff;padding:22px 28px;font-size:18px;font-weight:bold">☂ {{ $brand }}</td></tr>
    <tr><td style="padding:28px;font-size:15px;line-height:1.6">
      @yield('body')
    </td></tr>
    <tr><td style="padding:18px 28px;background:#f8f9fc;color:#8a93a6;font-size:12px">
      {{ $brand }} · {{ scloud_setting('address') }}<br>@if(scloud_setting('support_email'))¿Necesitas ayuda? Escríbenos a {{ scloud_setting('support_email') }}.@endif
    </td></tr>
  </table>
</td></tr></table>
</body></html>
