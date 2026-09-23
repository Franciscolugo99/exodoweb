# ÉXODO — pedidos web

Aplicación de pedidos para una hamburguesería, construida con PHP 8.1+ y MySQL/MariaDB. Está pensada para hosting compartido con PHP; no requiere Node.js ni procesos permanentes.

## Instalación

1. Crear una base MySQL/MariaDB con charset `utf8mb4`.
2. Configurar el document root del dominio en `public/`.
3. Abrir `/install/` y completar el instalador. Este crea el archivo local `config.php` y el bloqueo `public/install/install.lock`.
4. El instalador crea el primer usuario owner. No hay credenciales predeterminadas en el repositorio.

Para desarrollo local, iniciar MySQL y ejecutar `php -S localhost:8000 -t public` desde la raíz del proyecto.

## Antes de recibir pedidos reales

- El catálogo y los importes visibles en modo demostración son datos de muestra: confirmá y actualizá precios desde el panel.
- Cargá ingredientes y descripciones confirmados para cada producto.
- Revisá los datos del local, WhatsApp, horarios, delivery y costo de envío.
- Mercado Pago permanece desactivado hasta configurar credenciales reales y el webhook.

## Seguridad y archivos locales

`config.php`, `public/install/install.lock`, logs, backups y archivos de entorno no deben versionarse. El instalador genera la configuración local; guardá las credenciales fuera del repositorio.
