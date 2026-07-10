# Registro de Decisiones Técnicas — YIGM ERP

Decisiones aprobadas por el propietario del proyecto que complementan el Documento Maestro v1.0. Ninguna altera su esencia ni alcance.

| # | Fecha | Decisión | Detalle |
|---|---|---|---|
| 1 | 2026-07-10 | **Frontend: Vue 3 + Vite + Vue Router** | Resuelve la inconsistencia entre §3 (Nuxt 3) y §23.3 (estructura Vue con Views/Router). Se adopta la estructura de §23.3: Vue 3, Vite, Vue Router, Pinia, TypeScript y Tailwind CSS. SPA para red local. |
| 2 | 2026-07-10 | **SUNAT vía PSE/OSE con capa de abstracción** | La emisión electrónica se implementará detrás de `ElectronicInvoiceProviderInterface`, iniciando con un proveedor PSE/OSE (a seleccionar antes de la Fase 7). Migrable a emisión directa sin afectar módulos de negocio. |
| 3 | 2026-07-10 | **Código y BD en inglés, UI en español** | Clases, tablas, columnas, rutas API y archivos en inglés (estándar Symfony/Vue). Toda la interfaz de usuario, mensajes y documentación funcional en español. |
| 4 | 2026-07-10 | **Ventas v1: contado + pagos parciales/crédito** | El módulo de Ventas incluirá cuentas por cobrar: pagos parciales, saldos pendientes y estados de cobro. Impacta el diseño de Caja (cobros contra venta) y Dashboard ("Total facturado" vs "Total cobrado"). |

## Puntos pendientes de decisión

| # | Tema | Debe resolverse antes de |
|---|---|---|
| P1 | Proveedor PSE/OSE concreto (NubeFact, APISUNAT, etc.) | Fase 7 — Facturación |
| P2 | Moneda: ¿solo PEN o PEN/USD con tipo de cambio? | Fase 4 — Compras |
| P3 | Reservas: tiempo de vencimiento y bloqueo de stock | Fase 6 — Ventas |
| P4 | Impresión térmica 58/80 mm: PDF navegador vs ESC/POS | Fase 7 — Facturación |
| P5 | Política de copias de seguridad y servidor de despliegue | Fase 10 — Cierre |
