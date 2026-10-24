# Sombrilla Cloud

Convierte tu Sombrilla Studio en una plataforma para **vender instancias** a tus clientes.

## Qué incluye
- **Sitio público** con planes, carrito y checkout.
- Instancia propia por cliente, lista en segundos y con **acceso directo** desde su portal.
- **Subdominio** automático, **dominio propio** o dominio comprado (Cloudflare).
- **Pagos por transferencia**, **tickets** de soporte y **facturación** con el ERP.
- Suspender, reactivar y archivar instancias desde el panel.

## Límites por plan
Cada plan puede limitar la instancia del cliente:
- **Usuarios** que puede crear.
- **Plugins activos** a la vez (las dependencias cuentan).
- **Almacenamiento** (archivos + base de datos de la instancia).
- **Qué plugins puede usar**: todos, solo una selección, o todos menos algunos.

Se definen en el plan (*Planes › editar › Límites de la instancia*); vacío = sin límite. Al guardar el plan se aplican a las instancias que ya lo tienen, y también hay un botón «Aplicar límites del plan» en cada servicio. La instancia lo ve en sus pantallas de Usuarios y Plugins (barras «x de y»). El core no sabe nada de planes: Sombrilla Cloud solo le entrega límites (un `limits.json` firmado en el storage de la instancia).

## Requiere
- **Sombrilla ERP**, **Contactos**, **Inventario** y **Facturación**.

## Cómo empezar
1. Copia la configuración de ejemplo `ss-config.scloud-sample.php` a tu `ss-config.php` y ajústala (dominio base, secreto, Cloudflare…).
2. Crea tus planes en el panel de Sombrilla Cloud.
3. Prueba una compra completa antes de abrir al público.

> Las instancias de tus clientes se guardan en la carpeta `data/` del sitio.
