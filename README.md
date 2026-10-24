# Sombrilla Cloud

La **línea gráfica** de Sombrilla Cloud, empaquetada como un tema de [Webs](../webs/README.md). Toda la funcionalidad (planes, pedidos, instancias, pagos, tickets, panel del cliente…) vive en el plugin **Sombrilla Studio SaaS** (`plugins/sombrilla-studio-saas`); este plugin solo pone la cara.

## Qué incluye
- Tema `sombrilla-cloud` (`theme/`): cabecera con carrito y cuenta, pie con datos de contacto, estilos y paleta por defecto.
- Layouts `main` (páginas) y `wide` (páginas, blog, tienda y pantallas de Studio SaaS, contexto `saas`).
- Portada propia (`theme/views/saas/site/home.blade.php`): sustituye a la portada genérica de Studio SaaS mediante el filtro `scloud_view_name`.

## Requiere
- **Webs** y **Sombrilla Studio SaaS**.

## Cómo usarlo
1. Activa este plugin, *Webs* y *Sombrilla Studio SaaS*.
2. En *Webs › Temas* elige **Sombrilla Cloud**. Los colores y fuentes se ajustan en *Webs › Diseño*.
3. Marca, textos y datos de contacto se editan en *Studio SaaS › Ajustes*.

## Crear tu propia línea gráfica
Otra empresa que distribuya Sombrilla Studio como servicio hace lo mismo con su propio plugin-tema: registra un tema de Webs con un layout para el contexto `saas` (o `shop`) y, si quiere, vistas `saas/{vista}` propias (misma ruta que en el plugin: `saas/site/home.blade.php`) (ver `sombrilla-cloud.php`).
