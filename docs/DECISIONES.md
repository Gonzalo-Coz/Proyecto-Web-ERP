# Registro de Decisiones Técnicas — YIGM ERP

Decisiones aprobadas por el propietario del proyecto que complementan el Documento Maestro v1.0. Ninguna altera su esencia ni alcance.

| # | Fecha | Decisión | Detalle |
|---|---|---|---|
| 1 | 2026-07-10 | **Frontend: Vue 3 + Vite + Vue Router** | Resuelve la inconsistencia entre §3 (Nuxt 3) y §23.3 (estructura Vue con Views/Router). Se adopta la estructura de §23.3: Vue 3, Vite, Vue Router, Pinia, TypeScript y Tailwind CSS. SPA para red local. |
| 2 | 2026-07-10 | **SUNAT vía PSE/OSE con capa de abstracción** | La emisión electrónica se implementará detrás de `ElectronicInvoiceProviderInterface`, iniciando con un proveedor PSE/OSE (a seleccionar antes de la Fase 7). Migrable a emisión directa sin afectar módulos de negocio. |
| 3 | 2026-07-10 | **Código y BD en inglés, UI en español** | Clases, tablas, columnas, rutas API y archivos en inglés (estándar Symfony/Vue). Toda la interfaz de usuario, mensajes y documentación funcional en español. |
| 4 | 2026-07-10 | **Ventas v1: contado + pagos parciales/crédito** | El módulo de Ventas incluirá cuentas por cobrar: pagos parciales, saldos pendientes y estados de cobro. Impacta el diseño de Caja (cobros contra venta) y Dashboard ("Total facturado" vs "Total cobrado"). |
| 5 | 2026-07-12 | **Infraestructura de imágenes transversal (`Shared/Media`)** | Un único `ImageStorageService` valida, optimiza (redimensión/recorte GD) y almacena imágenes como RUTA (nunca binario en BD), con presets por módulo (`ImagePreset`: avatar, producto, moto, logo, documento). Reutilizable por todos los módulos; desbloquea parte del P4 (adjuntos/imágenes). Ficheros en `backend/public/uploads/` (ignorado en git, se respalda aparte). |
| 6 | 2026-07-12 | **Edición de perfil = autoservicio sin permiso** | Cada usuario administra SU propio perfil (`/api/v1/profile`) autenticado por JWT, sin permiso de la matriz §23.9. El **nombre de usuario permanece inmutable** (identificador de acceso y clave de auditoría), coherente con `UserService::update`. El historial de cambios lo cubre automáticamente `AuditListener` (§23.6). |
| 7 | 2026-07-12 | **Gobernanza: dos vías de trabajo (funcional vs consolidación)** | Se separa el trabajo en **Desarrollo funcional** (completar el alcance del Documento Maestro — prioridad actual) y **Consolidación técnica** (refactorizaciones, estandarización de respuestas, limpieza de código, mejoras visuales, rendimiento y demás cambios transversales). Las mejoras transversales se **documentan como backlog `CONS-xxx` en `PENDIENTES.md`** y se ejecutan en una fase de consolidación integral, **salvo riesgo crítico para la estabilidad o el despliegue** (se marcan con *gatillo* y se adelantan). El asistente sigue proponiendo mejoras al detectarlas, clasificándolas por prioridad y vía antes de implementar. Objetivo: cerrar primero el ERP funcional, evitando micro-optimizaciones que desvíen el alcance. |
| 8 | 2026-07-12 | **Modo de desarrollo autónomo con auto-backlog** | El asistente desarrolla de forma continua sin solicitar aprobación por cada observación. Toda mejora NO crítica detectada (arquitectura, rendimiento, seguridad, UX/UI, mantenibilidad, consistencia, limpieza) se **registra automáticamente** en `PENDIENTES.md`/Bitácora como backlog y el avance continúa. El asistente **solo se detiene** ante: (1) riesgo de pérdida de datos; (2) necesidad de modificar la arquitectura principal; (3) cambio de reglas de negocio ya aprobadas; (4) riesgo de romper compatibilidad con módulos existentes; (5) una decisión funcional exclusiva del propietario. **Orden de prioridad del proyecto:** 1) completar las fases adicionales del alcance; 2) completar las adiciones funcionales aprobadas (Documento de Adiciones); 3) rediseño visual completo (Frontend/UX-UI, identidad Integra/Yamaha); 4) integraciones API (DNI, RUC y arquitectura lista para SUNAT y futuras). La consolidación técnica profunda se pospone hasta cerrar lo funcional. |

## Puntos pendientes de decisión

| # | Tema | Debe resolverse antes de |
|---|---|---|
| P1 | Proveedor PSE/OSE concreto (NubeFact, APISUNAT, etc.) | Fase 7 — Facturación |
| P2 | Moneda: ¿solo PEN o PEN/USD con tipo de cambio? | Fase 4 — Compras |
| P3 | Reservas: tiempo de vencimiento y bloqueo de stock | Fase 6 — Ventas |
| P4 | Impresión térmica 58/80 mm: PDF navegador vs ESC/POS | Fase 7 — Facturación |
| P5 | Política de copias de seguridad y servidor de despliegue | Fase 10 — Cierre |
