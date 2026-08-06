# Pendientes técnicos — YIGM ERP

Este documento distingue **dos vías de trabajo** (ver Decisión #7 y Bitácora):

- **Desarrollo funcional** — completar el alcance del Documento Maestro. Prioridad actual.
- **Consolidación técnica** — refactorizaciones, estandarización, limpieza, rendimiento y mejoras transversales. Se agrupan aquí como backlog `CONS-xxx` y se ejecutan en la fase de Consolidación, **salvo que representen un riesgo crítico para la estabilidad o el despliegue** (en cuyo caso se marcan con *gatillo* y se adelantan).

Los bugs y deudas puntuales abiertas conservan su código `P-xxx`.

---

## Backlog Técnico de Consolidación

Ficha de cada ítem: descripción · impacto · prioridad · esfuerzo · momento recomendado · módulos afectados · beneficios · vía.

### CONS-001 · Código muerto del descuento global en Ventas — Prioridad BAJA

- **Descripción:** `SalesView` conserva el estado `globalDiscount` y `globalDiscountIsPercent`, y la BD conserva `sales.global_discount`, `sales.discount_authorized_by/at` y `roles.max_discount_percent`, todos sin uso tras retirar el descuento global/por monto fijo y la autorización (A2 descartado; solo se usa descuento porcentual por línea — A1).
- **Impacto:** Nulo en operación; genera ruido de mantenimiento y riesgo de reintroducir lógica retirada por error. Sin efecto en estabilidad.
- **Prioridad:** Baja.
- **Esfuerzo:** Bajo (limpieza en frontend; en BD, dejar las columnas o migración de limpieza opcional — no urge).
- **Momento recomendado:** Fase de Consolidación (bloque de limpieza de código). No antes.
- **Módulos afectados:** Ventas (frontend `SalesView`; opcionalmente entidad `Sale`/`Role` y una migración de limpieza).
- **Beneficios:** Coherencia entre código y decisiones aprobadas; menor superficie de error; lectura más clara del módulo más crítico.
- **Vía:** Consolidación.

### CONS-002 · Confidencialidad de documentos servidos por el servicio de imágenes — Prioridad MEDIA (gatillo ALTA)

- **Descripción:** `ImageStorageService` guarda en `public/uploads` y expone las rutas públicamente (`/uploads/...`). Es correcto para avatares/logo, pero el preset `DOCUMENT` (boletas, facturas, adjuntos) NO debe servirse público. Falta separar almacenamiento público de privado y servir lo privado por un controlador autenticado con control de acceso.
- **Impacto:** Hoy inexistente (el preset `DOCUMENT` aún no se usa). En cuanto se implementen adjuntos o PDFs servidos por esta vía, exponer comprobantes sería una fuga de datos sensibles.
- **Prioridad:** Media ahora; **Alta y bloqueante** en el momento en que se implemente cualquier adjunto/documento (P4).
- **Esfuerzo:** Medio (directorio privado fuera de `public/`, controlador que haga *stream* con verificación de permiso/propiedad, y política de nombres).
- **Momento recomendado:** **Obligatorio junto con la infraestructura de adjuntos/PDF (P4), antes de exponer el primer documento.** No diferible a "después del uso".
- **Módulos afectados:** `Shared/Media`, Facturación (comprobantes), y futuros adjuntos de Compras/Taller/Ventas.
- **Beneficios:** Evita exposición pública de comprobantes; cumple confidencialidad; deja la base correcta antes de crecer.
- **Vía:** Consolidación con gatillo (se adelanta si se aborda P4).

### CONS-003 · Contrato uniforme de respuestas de la API — Prioridad MEDIA

- **Descripción:** Conviven formatos de respuesta distintos: `{data, meta}` paginado, arrays sueltos y casos como `listRoles` que leen `r.data.data`. No hay un envoltorio de respuesta/paginación estándar.
- **Impacto:** Cada servicio del frontend maneja formas distintas; la inconsistencia crece con cada módulo nuevo. Sin efecto en estabilidad actual, pero encarece el mantenimiento y el crecimiento.
- **Prioridad:** Media.
- **Esfuerzo:** Medio-Alto (definir el contrato, adaptar controladores backend y servicios frontend; cuanto más tarde, más endpoints a alinear).
- **Momento recomendado:** Consolidación **temprana**, antes de que más módulos/reportes hereden el patrón disímil.
- **Módulos afectados:** Transversal (todos los controladores API, todos los `services` del frontend, paginación de `DataTable`).
- **Beneficios:** Menos código especial por servicio; onboarding más simple; base uniforme para exportaciones y búsqueda global (omnibox M-01).
- **Vía:** Consolidación.

### CONS-004 · Sistema unificado de notificaciones (Toast) — ✅ IMPLEMENTADO 2026-07-12

Resuelto en la fase de pulido: `composables/useToast.ts` (singleton reactivo) + `components/ui/ToastHost.vue` montado global en `App.vue`. Adoptado en Perfil, Listas de Precios, Promociones y Pagos; el resto de vistas migra de forma incremental (no bloqueante, no rompe las que aún usan mensajes inline). Ficha original abajo.

### CONS-004 (ficha original) · Sistema unificado de notificaciones (Toast) — Prioridad MEDIA

- **Descripción:** No existe un sistema de notificaciones/toast; el feedback de éxito/error se resuelve ad-hoc por vista (p. ej. los mensajes inline del perfil).
- **Impacto:** Experiencia inconsistente; cada vista reinventa el feedback; duplicación de patrones. Sin efecto en estabilidad.
- **Prioridad:** Media.
- **Esfuerzo:** Bajo-Medio (composable `useToast` + `ToastHost` en `DefaultLayout`; el Design System ya aporta estilos base).
- **Momento recomendado:** Consolidación (bloque de refinamiento UI/UX). Prepara el terreno para las notificaciones internas (M-02).
- **Módulos afectados:** Transversal frontend (layout + composable; adopción gradual por vista, sin romper las existentes).
- **Beneficios:** Feedback uniforme, menos duplicación, mayor percepción de calidad, base reutilizable para M-02.
- **Vía:** Consolidación.

### CONS-006 · Selectores de producto con autocompletado server-side — Prioridad BAJA

- **Descripción:** en Listas de Precios (A4) y en el detalle de Ventas los selectores de producto (repuestos/modelos) cargan hasta 100 ítems en memoria. Con catálogos grandes conviene un buscador con autocompletado paginado en el servidor.
- **Impacto:** UX y rendimiento con volúmenes altos; hoy nulo. Sin efecto en estabilidad.
- **Prioridad:** Baja. **Esfuerzo:** Medio. **Momento:** Consolidación (UX), o antes si el catálogo real supera ~100 productos.
- **Módulos afectados:** Ventas, Pricing (frontend); endpoints de búsqueda ya existentes en Inventory/Motorcycle.
- **Beneficios:** escala a catálogos reales sin degradar la carga de los formularios.
- **Vía:** Consolidación.

### CONS-005 · Deuda de migraciones de inventario — ver P-002 (reclasificada)

Corresponde a la observación de "deuda de migraciones". Ya existe abajo como **P-002**; se integra a esta vía con las siguientes claves: **Prioridad Media (Alta como gatillo antes de despliegue o de cualquier reconstrucción de BD)**, **esfuerzo Bajo**, **módulos: Inventario (`spare_parts`, `spare_part_compatibility`, `kardex_movements`)**, **beneficio: reconstrucción reproducible y respaldos/migraciones seguras**, **vía: Consolidación con gatillo pre-despliegue**. Detalle técnico y diagnóstico en P-002.

---

## P-002 · Historial de migraciones sin DDL de inventario — ABIERTO (no bloqueante)

**Situación (2026-07-11):** las tablas `spare_parts`, `spare_part_compatibility` y `kardex_movements` existen en la BD y funcionan, pero ninguna migración del historial las contiene (secuela del incidente "Duplicate table"). `make:migration` no genera nada porque BD y entidades ya están en sincronía.

**Impacto:** ninguno en la operación actual. Solo afectaría una reconstrucción desde cero (`doctrine:migrations:migrate` en BD vacía no crearía esas 3 tablas).

**Solución cuando se retome:** en una BD de prueba vacía ejecutar todas las migraciones, correr `make:migration` ahí (generará el DDL de inventario faltante) y añadir esa migración al repositorio; o extraer el DDL real con `pg_dump --schema-only`.

**Verificación previa recomendada:** `php bin/console doctrine:schema:validate` debe responder "The database schema is in sync with the mapping files".

## P-001 · No se puede recrear usuario eliminado ("javier") — ABIERTO

**Síntoma:** tras crear la migración `Version20260711001000` (índices únicos parciales `WHERE deleted_at IS NULL`), la creación del usuario "javier" sigue fallando en la interfaz.

**Hipótesis, en orden de probabilidad:**

1. La migración no llegó a ejecutarse (`doctrine:migrations:migrate` no corrido o cancelado en la confirmación).
2. El correo del nuevo "javier" coincide con el del eliminado y el mensaje corresponde al índice de email (mismo problema, otra columna) — descartable si la migración corrió.
3. El error mostrado no es de unicidad sino de validación (contraseña < 8, roles, etc.) — el modal muestra el `detail` de la API; revisar el texto exacto.

**Diagnóstico cuando se retome:**

```powershell
# 1. ¿Se ejecutó la migración?
php bin/console doctrine:migrations:status
# 2. ¿Los índices son parciales? (deben mostrar "WHERE (deleted_at IS NULL)")
php bin/console dbal:run-sql "SELECT indexname, indexdef FROM pg_indexes WHERE tablename IN ('users','roles');"
# 3. ¿Qué registros 'javier' existen (incluidos eliminados)?
php bin/console dbal:run-sql "SELECT id, username, email, deleted_at FROM users;"
```

**Solución esperada:** si `indexdef` no muestra el `WHERE`, ejecutar `php bin/console doctrine:migrations:migrate`. Si ya lo muestra, capturar el error exacto de la respuesta HTTP (pestaña Red del navegador) y analizar.
