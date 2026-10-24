<?php

// ── Command Palette ─────────────────────────────────────────────────────────
ss_command('scloud.dashboard', 'Sombrilla Cloud')->group('Sombrilla Cloud')->icon('fa fa-cloud')->keywords('hosting', 'panel')->permission('scloud_view')->url(url('/scloud'));
ss_command('scloud.orders', 'Pedidos de hosting')->group('Sombrilla Cloud')->icon('fa fa-shopping-cart')->keywords('ordenes', 'ventas')->permission('scloud_view')->url(url('/scloud/pedidos'));
ss_command('scloud.payments', 'Pagos por validar')->group('Sombrilla Cloud')->icon('fa fa-money-check')->keywords('transferencias', 'comprobantes')->permission('scloud_view')->url(url('/scloud/pagos'));
ss_command('scloud.services', 'Servicios de hosting')->group('Sombrilla Cloud')->icon('fa fa-server')->keywords('cpanel', 'cuentas', 'whm')->permission('scloud_view')->url(url('/scloud/servicios'));
ss_command('scloud.tickets', 'Tickets de soporte')->group('Sombrilla Cloud')->icon('fa fa-life-ring')->keywords('soporte', 'ayuda')->permission('scloud_view')->url(url('/scloud/tickets'));
ss_command('scloud.plans', 'Planes de hosting')->group('Sombrilla Cloud')->icon('fa fa-layer-group')->permission('scloud_manage_plans')->url(url('/scloud/planes'));
