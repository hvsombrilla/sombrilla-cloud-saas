<?php

// ── Command Palette ─────────────────────────────────────────────────────────
ss_command('scloud.dashboard', 'Sombrilla Cloud')->group('Sombrilla Cloud')->icon('ph ph-cloud')->keywords('instancias', 'panel')->permission('scloud_view')->url(url('/scloud'));
ss_command('scloud.orders', 'Pedidos')->group('Sombrilla Cloud')->icon('ph ph-shopping-cart')->keywords('ordenes', 'ventas')->permission('scloud_view')->url(url('/scloud/pedidos'));
ss_command('scloud.payments', 'Pagos por validar')->group('Sombrilla Cloud')->icon('ph ph-money')->keywords('transferencias', 'comprobantes')->permission('scloud_view')->url(url('/scloud/pagos'));
ss_command('scloud.services', 'Instancias')->group('Sombrilla Cloud')->icon('ph ph-hard-drives')->keywords('instancias', 'subdominio', 'dominio', 'tenants')->permission('scloud_view')->url(url('/scloud/servicios'));
ss_command('scloud.tickets', 'Tickets de soporte')->group('Sombrilla Cloud')->icon('ph ph-lifebuoy')->keywords('soporte', 'ayuda')->permission('scloud_view')->url(url('/scloud/tickets'));
ss_command('scloud.plans', 'Planes')->group('Sombrilla Cloud')->icon('ph ph-stack')->permission('scloud_manage_plans')->url(url('/scloud/planes'));
