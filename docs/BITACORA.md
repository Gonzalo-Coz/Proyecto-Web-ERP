# Bitácora Técnica — YIGM ERP

## Fase 0 — Preparación del Entorno (CERRADA · 2026-07-10)

**Entorno validado:** Windows 11 · PHP 8.5.8 · Symfony 7.4.14 · Composer 2.10.2 · PostgreSQL 17.5 · Node.js 22 LTS · pnpm · Git · VS Code.

**Hitos completados:**

1. `composer install` exitoso (incidencia resuelta: habilitación de `ext-sodium` y extensiones PostgreSQL en `php.ini`; `lcobucci/jwt 5.6.0` es la única versión compatible con PHP 8.5).
2. Variables de entorno correctas (`debug:dotenv`): `DATABASE_URL` con `serverVersion=17`, secretos reales en `.env.local` (no versionado).
3. Base de datos `yigm_erp` creada vía `doctrine:database:create`; conexión verificada contra PostgreSQL 17.5.
4. Par de claves JWT generado (`config/jwt/*.pem`, fuera de Git); `lexik:jwt:check-config` OK.
5. Servidor local operativo: `symfony server:start --port=8000`.

**Decisiones operativas registradas:**

- NO usar `symfony server:start -d` en Windows (bloqueo del archivo de log del daemon). Comando oficial del proyecto: `symfony server:start --port=8000`.
- Pruebas de API en PowerShell con `Invoke-RestMethod` (NO `curl.exe -d "{...}"`: PowerShell mutila las comillas escapadas del JSON).
- **Regla de migraciones:** tras cada `make:migration`, ejecutar INMEDIATAMENTE `doctrine:migrations:migrate` y comprobar con `doctrine:migrations:status` que no queden pendientes. Nunca generar una migración nueva con otras sin ejecutar (causó el incidente "Duplicate table: motorcycle_models" del 2026-07-11: la migración de Fase 3 se generó con la de Fase 2 aún pendiente y duplicó sus CREATE TABLE; se resolvió eliminando la migración sucia y regenerándola).
- **Estándar del proyecto — unicidad + soft delete:** los índices únicos de entidades con eliminación lógica se definen como índices parciales de PostgreSQL (`WHERE deleted_at IS NULL`), permitiendo reutilizar el identificador de un registro dado de baja sin perder el histórico. Aplicado a users (username, email) y roles (code). Pendiente evaluar excepción para VIN en Fase 2.
- Gestor de paquetes frontend: **pnpm**.
- Advertencias informativas del Symfony CLI (TLS, listen 127.0.0.1) aceptadas sin acción para entorno de red local.

**No repetir:** composer install, configuración PHP/Symfony/PostgreSQL/Doctrine/JWT/variables/BD/servidor. Todo forma parte de la línea base.

---

## Estado de avance por fases (actualizado 2026-07-10)

| Fase | Estado |
|---|---|
| 0 — Entorno + Security + UI base | ✅ Completa y validada |
| 1 — Clientes, Proveedores, Catálogos | ✅ Código completo (backend + frontend) |
| 2 — Motocicletas (modelos + unidades VIN) | ✅ Código completo — **pendiente ejecutar migración, sync-permissions y prueba funcional** |
| 3 — Repuestos e Inventario (Kardex, compatibilidades) | ✅ Código completo — pendiente migración, sync-permissions y prueba |
| 4 — Compras | ✅ Código completo (compra directa) — pendiente migración, sync-permissions y prueba |
| 5 — Caja | ✅ Código completo — pendiente migración, sync-permissions y prueba |
| 6 — Ventas (cotización→venta, CxC, integración total) | ✅ Código completo — pendiente migración, sync-permissions y prueba |
| 7 — Facturación SUNAT | ✅ Código completo con **proveedor SIMULADO** — pendiente migración, sync-permissions, prueba y adaptador PSE/OSE real (P1) |
| 8 — Taller | ✅ Código completo — pendiente migración, sync-permissions y prueba |
| 9 — Reportes y Dashboard | ✅ Código completo — pendiente sync-permissions y prueba (SIN migración: no hay entidades nuevas) |
| 10 — Cierre | 🔶 Parcial: Configuración General en BD (empresa, IGV configurable aplicado a Ventas/Compras, RUC en proveedor SUNAT), 12 reportes completos del §16, pruebas unitarias de reglas críticas (CxC y stock). Pendiente: adaptador PSE/OSE real (P1), moneda USD (P2), liberación automática de reservas (P3), XLSX/PDF nativo e impresión de comprobantes (P4), backups/despliegue (P5), adjuntos/imágenes, P-001/P-002. |

Ejecutar pruebas: `php bin/console` no aplica — usar `vendor\bin\phpunit` desde `backend/`. |

Decisiones Fase 9: indicadores calculados en vivo con SQL directo (suficiente a esta escala; migrar a tablas de resumen por eventos cuando crezca el volumen — la API no cambia). Actividad reciente tomada de audit_logs. Reportes v1: 6 de los 13 mínimos (ventas, compras, caja, kardex, taller, comprobantes) con filtros de fecha; los restantes (clientes, proveedores, motos, repuestos, utilidades, auditoría, inventario valorizado) se agregan sobre el mismo patrón en el cierre. Exportación: CSV con BOM UTF-8 generado en el navegador (abre en Excel); XLSX/PDF nativos pendientes con la infraestructura de exportación (P4). |

Decisiones Fase 8: la orden admite unidad propia (expediente digital) o motocicleta EXTERNA (descripción + placa). Los repuestos descuentan stock AL AGREGARSE a la orden (Kardex TALLER) y se devuelven al retirarse. Solo las unidades en estado DISPONIBLE pasan a EN_TALLER (las vendidas conservan su estado; el vínculo alimenta el expediente). Facturación de la orden = venta COMPLETADA con una línea de servicio por el total (los repuestos no se descuentan doble); el cobro va por Ventas/Caja. Mecánico como texto libre en v1 (selector de usuarios con rol Mecánico: mejora futura). Fotografías/adjuntos de la orden: pendiente junto a infraestructura de adjuntos. Órdenes entregadas: inmutables. |

Decisiones Fase 7: emisión detrás de `ElectronicInvoiceProviderInterface` con `SimulatedProvider` activo (alias en services.yaml — cambiar SOLO ahí al contratar el PSE/OSE). Serie/correlativo asignados con bloqueo pesimista ANTES del envío: si el proveedor falla, el comprobante conserva su número y se reenvía (nunca se pierde correlativo). Comprobante = snapshot inmutable del cliente y totales; solo `applyProviderResult` lo muta. v1 emite Boleta (03) y Factura (01, exige RUC); NC/ND tienen estructura lista pero sin flujo de emisión (requiere definir motivos SUNAT — Documento de Adiciones). RUC de la empresa temporal en SimulatedProvider: saldrá de Configuración (§23.10). Diseño de impresión (A4/58mm/80mm, logo editable): pendiente junto con infraestructura PDF (P4). |

Decisiones Fase 6: la venta es un documento único que evoluciona (COTIZACION → RESERVA → COMPLETADA / ANULADA). La reserva bloquea unidades (RESERVADA) sin comprometer stock de repuestos; el vencimiento es informativo en v1 (P3 sigue abierto para liberación automática). Cobros (CxC): permitidos en RESERVA (adelantos) y COMPLETADA; cada cobro exige caja abierta y genera INGRESO con referencia al número de venta; no se admite cobrar más que el saldo. Anulación bloqueada si hay cobros (primero egreso manual en Caja). Garantía: se deriva de fecha de venta + meses del modelo (sin entidad propia por ahora). Comprobante SUNAT: Fase 7 sobre estas ventas. |

Decisiones Fase 5: una sola caja física en v1 (única sesión ABIERTA a la vez). El arqueo calcula el efectivo esperado solo con movimientos en efectivo (método null o código CASH); los demás medios se totalizan aparte. Los movimientos son inmutables; las correcciones se hacen con contramovimientos. `CashService::registerMovement` será invocado por Ventas para los cobros. |

Decisiones Fase 4: v1 implementa **Compra Directa** transaccional (repuestos → stock+Kardex vía StockService; unidades de moto → actualiza proveedor/fecha/precio de compra del expediente). Anulación revierte stock (DEVOLUCION) y se bloquea si la unidad ya se vendió o el stock ya se consumió. IGV 18% fijo temporal (pasará a Configuración §23.10). Orden de Compra con recepción diferida, facturas de proveedor y notas de crédito: pendientes de fase posterior (documentar en Documento de Adiciones si cambia el alcance). Correlativo C-000001 por conteo (mono-usuario v1; con concurrencia real pasará a secuencia PG). |

Decisiones Fase 3: stock embebido en `spare_parts` (almacén único v1; multialmacén lo extraerá a tabla propia). `StockService` es el ÚNICO punto que modifica stock — transaccional, con bloqueo pesimista contra sobreventa, y genera el Kardex; Compras/Ventas/Taller lo invocarán. El stock inicial entra por Ajuste o Compra, nunca por edición directa. Importación desde Excel (§10): pendiente junto con la infraestructura de adjuntos/exportación. |

Pendientes abiertos: ver `PENDIENTES.md` (P-001 usuario javier). Adjuntos/imagen de modelo y exportación PDF/Excel de tablas: infraestructura transversal pendiente, se abordará antes de Reportes (Fase 9).

Decisión Fase 2: el VIN tiene unicidad TOTAL (incluye eliminados, nunca se reutiliza); número de motor igual; código interno con unicidad parcial. Estados VENDIDA / EN_TALLER / GARANTIA solo los asigna el sistema (ventas/taller); una unidad VENDIDA es inmutable y no eliminable.

---

## Fase 0 — Parte 2: Módulo Security + transversales (BACKEND VALIDADO · 2026-07-10)

Pruebas superadas: login JWT (token + user con permisos), `GET /auth/me` con Bearer, 401 sin token. Migración inicial ejecutada (6 tablas: users, roles, permissions, user_roles, role_permissions, audit_logs). Usuario administrador creado con rol ADMIN superadmin.

### Alcance implementado

Implementación según §22, §23.6, §23.7, §23.9 del Documento Maestro:

- `Shared/Doctrine`: timestamps automáticos, soft delete (interfaz + trait + filtro SQL global).
- `Shared/Auditing`: registro automático de auditoría (usuario, IP, módulo, entidad, acción, valores anteriores/nuevos).
- `Module/Security`: entidades `User`, `Role`, `Permission` (permisos dinámicos `modulo.pantalla.accion`), login JWT, voter de permisos, comando de creación de administrador.

---

## Fase de Evolución — Edición de perfil + Infra de imágenes (CÓDIGO COMPLETO · 2026-07-12)

Implementa la mejora funcional "Edición completa del perfil de usuario" del Documento Maestro y, como base transversal, la infraestructura de imágenes (§Almacenamiento / §Gestión de imágenes). Ver Decisiones #5 y #6.

**Backend**

- `Shared/Media`: `ImageStorageService` (validación MIME/tamaño ≤ 5 MB, optimización GD con recorte COVER/CONTAIN sin ampliar, guardado como ruta en `public/uploads/<categoría>/<shard>/<hash>.webp`, borrado seguro y `publicUrl()`), `ImagePreset` (dimensiones por módulo) e `ImageFit`. Degrada con elegancia: si falta `ext-gd`, guarda el original validado.
- `User`: nuevas columnas `phone` y `avatar_path` (nullable) — migración aditiva `Version20260712090000`. **Ejecutar `doctrine:migrations:migrate` tras `git pull`.**
- `Module/Security`: `ProfileController` (`GET/PATCH /api/v1/profile`, `PATCH /api/v1/profile/password`, `POST/DELETE /api/v1/profile/avatar`) con `#[CurrentUser]` y SIN permiso de la matriz (autoservicio). `ProfileService`, `ProfilePayload`, `ChangePasswordPayload`.
- `/auth/me`, listener de login y `UserService::toArray` extendidos con `phone` y `avatarUrl` (coherencia de contrato en toda la app).
- Historial de cambios del perfil: automático vía `AuditListener` (la contraseña ya está en `SENSITIVE_FIELDS`, nunca se audita en claro).

**Frontend**

- `services/profile.ts`, `types/profile.ts`, ruta `/account/profile` (sin permiso), `stores/auth.applyProfile()` (refleja nombre/correo/teléfono/avatar en la cabecera sin re-login).
- `views/account/ProfileView.vue`: tarjeta de foto (subir/cambiar/quitar con validación cliente), datos personales (usuario en solo lectura) y cambio de contraseña. Reutiliza `FormField`, `card`, `btn-*`, `form-input` (cero componentes UI nuevos).
- Cabecera: el avatar enlaza a "Mi perfil" y muestra la foto si existe.
- `vite.config.ts`: proxy `/uploads` → backend (dev). En producción el servidor web sirve `public/uploads` directamente.

**Puesta al día tras `git pull` (orden):**

```powershell
cd D:\Development\ERP\YIGM-ERP\backend
php bin/console doctrine:migrations:migrate      # crea users.phone y users.avatar_path
php bin/console doctrine:schema:validate         # debe decir "in sync"
php bin/console lint:container                    # valida DI (incluye Shared/Media)
# (opcional) habilitar ext-gd en php.ini para que la optimización de imágenes actúe
cd ..\frontend
pnpm dev
```

**Notas de arquitectura / verificación pendiente en entorno del usuario:**

- No hay `php` en el sandbox de desarrollo del asistente: falta ejecutar `php -l`, `lint:container` y una prueba funcional (subir avatar, cambiar datos y contraseña) en Windows.
- `ImagePreset`/`ImageFit` son enums bajo el glob `App\:` de `services.yaml`; Symfony los omite del contenedor (no instanciables). Confirmar con `lint:container`.
- **Decisión abierta para el propietario:** el nombre de usuario se mantuvo INMUTABLE en el perfil (ver Decisión #6). El Documento Maestro lo listaba entre los campos editables; si se desea permitir su cambio, requiere instrucción explícita y evaluar impacto en JWT (identificador) y auditoría.
- Sin dependencias nuevas de Composer/npm (se usa GD nativo de PHP): coherente con "no agregar librerías innecesarias".

---

## Gobernanza del desarrollo — Dos vías de trabajo (2026-07-12)

Se adopta un modelo de trabajo de dos vías (Decisión #7) para mantener el desarrollo ordenado y evitar desviaciones del alcance:

1. **Desarrollo funcional** — completar los módulos y funcionalidades del Documento Maestro. **Es la prioridad de esta etapa.**
2. **Consolidación técnica** — refactorizaciones, estandarización de respuestas de la API, limpieza de código, mejoras visuales, optimización de rendimiento y demás mejoras transversales.

**Regla de clasificación:** toda mejora transversal detectada se registra como backlog `CONS-xxx` en `PENDIENTES.md` (con ficha: descripción, impacto, prioridad, esfuerzo, momento recomendado, módulos afectados, beneficios, vía) y se difiere a la fase de Consolidación integral. **Excepción:** si una mejora representa un riesgo crítico para la estabilidad del sistema o para un futuro despliegue, se marca con *gatillo* y se adelanta (p. ej. CONS-002 antes de exponer documentos; CONS-005/P-002 antes del despliegue).

**Rol del asistente:** además de desarrollar, actúa como arquitecto — sigue proponiendo mejoras de arquitectura, seguridad, mantenibilidad, rendimiento y UX cuando las detecte, pero **antes de implementarlas** las clasifica por prioridad e indica a qué vía pertenecen. No se inicia trabajo de consolidación sin aprobación explícita.

**Backlog inicial registrado (2026-07-12):** CONS-001 (código muerto en Ventas), CONS-002 (confidencialidad de documentos del servicio de imágenes), CONS-003 (contrato uniforme de respuestas API), CONS-004 (sistema unificado de notificaciones/Toast), CONS-005 → P-002 (deuda de migraciones de inventario). Detalle en `PENDIENTES.md`.

### Actualización de modo — Desarrollo autónomo (2026-07-12, Decisión #8)

A partir de aquí el desarrollo es **autónomo y continuo**: las mejoras no críticas detectadas se registran solas en el backlog (`CONS-xxx`) y el avance no se interrumpe. El asistente **solo se detiene** ante los 5 casos críticos de la Decisión #8 (pérdida de datos, cambio de arquitectura principal, cambio de reglas de negocio aprobadas, riesgo de romper compatibilidad, o decisión funcional exclusiva del propietario).

**Orden de prioridad vigente:** (1) fases adicionales del alcance → (2) adiciones aprobadas del Documento de Adiciones → (3) rediseño visual completo → (4) integraciones API (DNI, RUC; arquitectura lista para SUNAT). Consolidación técnica profunda pospuesta.

**Gates funcionales identificados (decisiones del propietario, requeridas cuando llegue su turno):** aprobación formal del Lote 1 de adiciones A3–A6 (hoy "pendiente de aprobación" en el Documento de Adiciones); proveedor PSE/OSE real para SUNAT (P1); moneda PEN vs PEN+USD (P2); proveedor y credenciales para consulta DNI/RUC (apis.net.pe u otro).

---

## Lote 1 · Adición A3 — Historial de precios (CÓDIGO COMPLETO · 2026-07-12)

Aprobado el Lote 1 completo (A3–A6) por el propietario; implementación incremental (una adición completa y verificada antes de la siguiente). Cierra A3.

**Backend**

- `Shared/Pricing`: entidad `PriceHistoryEntry` (tabla `price_history`, append-only, `#[ORM\Entity(readOnly: true)]`, índices por sujeto y fecha), `PriceHistoryRepository`, `PriceHistoryService` (registro idempotente —solo si el precio cambió— + reporte con filtros y paginación), `PriceHistoryController` (`GET /api/v1/pricing/price-history`, permiso `pricing.history.view`).
- Alimentación transversal desde `SparePartService` (precio de **venta**) y `ModelService` (precio de **referencia**), en create y update, capturando el **motivo** (`priceChangeReason`, campo transitorio en los payloads, no persistido en la entidad).
- Nuevo permiso `pricing.history.view/export` en el catálogo (`SyncPermissionsCommand`). **Ejecutar `app:security:sync-permissions` tras desplegar.**
- `PriceHistoryEntry` añadido a `AuditListener::isIgnored()` (ya es su propia bitácora; evita duplicar el registro).
- Migración `Version20260712120000` (crea `price_history`). **Ejecutar `doctrine:migrations:migrate`.**

**Frontend**

- `services/pricing.ts`, `types/pricing.ts`, vista `views/pricing/PriceHistoryView.vue` (DataTable reutilizado con slots, filtros por tipo/fechas/búsqueda, variación % calculada en vivo), ruta `/pricing/price-history` y entrada en el sidebar (grupo Inventario).
- Campo "Motivo del cambio de precio" en los formularios de Repuestos y Modelos, visible **solo al editar y cuando el precio cambió** (computed `salePriceChanged`/`referencePriceChanged`).

**Verificación (sin regresiones):** no hay construcciones manuales de `SparePartService`/`ModelService` (todo autowiring; el nuevo dep `PriceHistoryService` se inyecta solo); `toArray` de ambos servicios intacto; el test `SparePartStockTest` no depende del constructor. Falta en entorno del usuario: `php bin/console lint:container`, `doctrine:migrations:migrate`, `app:security:sync-permissions` y prueba funcional (editar un precio con motivo → verlo en el reporte). Sin dependencias nuevas.

**Reutilización deliberada (DRY):** un único servicio de precios sirve a repuestos y motos y servirá a A4 (listas de precios); `DataTable`, `FormField` y el Design System reutilizados sin componentes nuevos.

**Puesta al día tras `git pull`:** `doctrine:migrations:migrate` → `app:security:sync-permissions` → asignar el permiso `pricing.history.view` a los roles que corresponda (el superadmin ya lo tiene por comodín) → `pnpm dev`.

---

## Lote 1 · Adición A4 — Listas de precios (CÓDIGO COMPLETO · 2026-07-12)

**Backend** — nuevo `Module/Pricing` (depende de Customer/Inventory/Motorcycle; historial de precios permanece en `Shared/Pricing` porque es un log de bajo nivel): entidades `PriceList` (soft-delete, código único parcial, `is_default`) y `PriceListItem` (precio por producto polimórfico spare_part/motorcycle_model, único por lista+producto); `PriceListService` (CRUD, garantiza una sola lista predeterminada, valida/etiqueta productos reutilizando los repos existentes); `PriceResolver` (**punto único** de precio: lista del cliente → predeterminada → precio base del producto); `PriceListController` (CRUD con permisos `pricing.price_lists.*` + `GET /pricing/resolve` sin permiso específico, solo devuelve precio). `Customer` gana `price_list_id` (FK ON DELETE SET NULL) + payload/servicio/`toArray`. Permisos `pricing.price_lists.view/create/edit/delete` en el catálogo. Migración `Version20260712150000` (price_lists, price_list_items, customers.price_list_id).

**Frontend** — `services/pricing` y `types/pricing` ampliados; `views/pricing/PriceListsView.vue` (gestión de listas con editor de precios por producto), ruta y entrada en sidebar (grupo Inventario); selector de lista en el formulario de Clientes; **Ventas** prellena el precio de cada línea vía `PriceResolver` al elegir producto o cambiar cliente (`resolveLinePrice`), **sin pisar** un precio ya escrito a mano y sin alterar la regla de negocio (el backend sigue tomando `unitPrice` de la línea).

**Compatibilidad / sin regresiones:** ningún servicio se construye a mano (autowiring; `CustomerService` recibe `PriceListRepository` solo); `toArray` de Customer solo se amplía (campos nuevos, no rompe consumidores); Ventas mantiene el override manual. Falta en entorno del usuario: `doctrine:migrations:migrate`, `app:security:sync-permissions`, `lint:container` y prueba funcional (crear lista → asignarla a un cliente → nueva venta a ese cliente muestra el precio de la lista). Sin dependencias nuevas.

**Nota (backlog, no crítica):** los selectores de producto de la lista y de Ventas cargan hasta 100 ítems; con catálogos grandes conviene un buscador con autocompletado server-side — registrado como mejora de consolidación (UX), no bloqueante.

---

## Lote 1 · Adición A5 — Promociones (CÓDIGO COMPLETO · 2026-07-12)

**Backend** — `Module/Promotion`: entidad `Promotion` (soft-delete; `type` DISCOUNT/BONUS; `scope_type` ALL/BRAND/CATEGORY/MODEL + `scope_ref_id`; para BONUS: producto+cantidad; vigencia `start_date`/`end_date`); `PromotionResolver` (**punto único**: para un producto calcula el mejor % de descuento y las bonificaciones, resolviendo marca/categoría/modelos del repuesto o del modelo); `PromotionService` (CRUD con validación por tipo y fechas, etiqueta el producto de bonificación reutilizando los repos); `PromotionController` (CRUD `sales.promotions.*` + `GET /promotions/applicable` sin permiso específico para el prellenado). Permisos `sales.promotions.view/create/edit/delete`. Migración `Version20260712180000` (tabla `promotions`).

**Frontend** — `services/promotions`, `types/promotions`, `views/sales/PromotionsView.vue` (gestión con alcance y bonificación dinámicos), ruta `/promotions` y sidebar (grupo Comercial). **Ventas** ahora, al elegir producto o cambiar cliente, prellena el precio (A4) **y** aplica el % de descuento de la promoción vigente (A5) en el mismo cuadro porcentual de A1; las bonificaciones aparecen como chips "Agregar" que insertan una línea a S/ 0.00 (repuestos en v1; moto se agrega manual).

**Compatibilidad / sin regresiones:** autowiring en todo; **no se tocó `SaleService`** (regla de negocio intacta: el backend sigue tomando `unitPrice`/`discountPercent` de la línea; A5 solo prellena en el frontend). Falta en entorno del usuario: `doctrine:migrations:migrate`, `app:security:sync-permissions`, `lint:container` y prueba funcional (crear promoción por marca → nueva venta de un producto de esa marca prellena el %). Sin dependencias nuevas.

**Alineado con tu preferencia de UX:** el descuento porcentual es un único cuadro numérico (%) que el sistema rellena automáticamente; el usuario solo ve/edita ese valor.

---

## Lote 1 · Adición A6 — Arquitectura de pasarelas de pago (CÓDIGO COMPLETO · 2026-07-12) — LOTE 1 CERRADO

**Backend** — `Module/Payment` replicando el patrón del proveedor SUNAT (decisión #2): `PaymentGatewayInterface` + `ManualGateway` (v1) + `GatewayResult`, con **alias en `services.yaml`** (`PaymentGatewayInterface → ManualGateway`) — al contratar un agregador se cambia SOLO ese alias. Entidad `payment_transactions` (medio YAPE/PLIN/CARD/TRANSFER/OTHER, monto, moneda PEN, estado PENDING/APPROVED/REJECTED/VOIDED, nº operación, gateway, `response_payload` JSON, vínculo suelto a la venta por id+número, `created_by`/`validated_by`/`validated_at`). `PaymentGatewayService` (registra vía la pasarela activa → PENDING; validación manual aprobar/rechazar). `PaymentTransactionController` (permisos `payments.gateway.view/create/validate`). Migración `Version20260712210000`.

**Frontend** — `services/payments`, `types/payments`, `views/payments/PaymentsView.vue` (registro + filtro por estado + aprobar/rechazar), ruta `/payments/transactions` y sidebar (grupo Finanzas).

**Compatibilidad:** módulo autónomo; **no toca Ventas ni Caja** (la conciliación con la venta se hará al integrar una pasarela real — decisión futura del propietario). `GatewayResult` es value object no autowired (precedente: `ProviderResult` de Invoicing). Sin dependencias nuevas.

---

## Cierre Lote 1 (A3–A6) · Comandos de puesta al día (2026-07-12)

Aplicar tras `git pull`. Se estandariza entregar estos comandos con cada cambio (a pedido del propietario). Todas las migraciones nuevas usan `GENERATED BY DEFAULT AS IDENTITY` (convención del proyecto) y son aditivas.

```powershell
# 1) Backend — migraciones (crea price_history, price_lists, price_list_items,
#    customers.price_list_id, promotions, payment_transactions)
cd D:\Development\ERP\YIGM-ERP\backend
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:migrations:status          # sin migraciones pendientes
php bin/console doctrine:schema:validate            # "in sync"

# 2) Permisos nuevos (pricing.*, sales.promotions.*, payments.gateway.*)
php bin/console app:security:sync-permissions

# 3) Validación de contenedor (incluye nuevos módulos, alias de pasarela y enums)
php bin/console lint:container

# 4) Frontend
cd ..\frontend
pnpm dev
```

El superadmin ve los módulos nuevos por comodín `*`; a los demás roles hay que asignarles los permisos `pricing.*`, `sales.promotions.*` y `payments.gateway.*` en Roles y Permisos.

---

## Fase de pulido (prioridad 3) — CONS-004 Toast unificado (2026-07-12)

Cerrado lo funcional (Lote 1), arranca el pulido visual/UX. Primer paso, transversal y sin dependencia de gustos: sistema de notificaciones unificado.

- `composables/useToast.ts` (estado singleton reactivo, auto-dismiss) + `components/ui/ToastHost.vue` (top-right, tipos success/error/info con transiciones, usa el Design System) montado una vez en `App.vue` (cubre login y app).
- Adoptado en Perfil, Listas de Precios, Promociones y Pagos (antes tenían feedback ad-hoc o nulo, p. ej. aprobar/rechazar pago). Solo frontend, **no rompe** las vistas que aún usan mensajes inline; la migración del resto es incremental.
- Sin dependencias nuevas. Backlog CONS-004 marcado como implementado.

**Nota:** el login actual ya cumple el Documento Maestro (panel de marca, degradados, halos, slots para foto y logos transparentes); el rediseño visual profundo se hará con las ideas del propietario. Pendiente de definición: proveedor de APIs DNI/RUC (el propietario lo está investigando).

---

## Pulido pre-lanzamiento (2026-07-12)

- **Clientes:** eliminado el campo "Celular" (duplicaba Teléfono); la columna `mobile` se conserva en BD (no destructivo).
- **Repuestos/Modelos:** creación de marca/categoría al vuelo con `CatalogSelect` (botón "+"); "Unidad de Medida" pasó a desplegable (`constants/units.ts`).
- **Ubigeo (distrito/provincia/departamento) en cascada:** `Shared/Ubigeo` (UbigeoService lee `backend/resources/ubigeo/*.json`, filtra por `id_padre_ubigeo`; UbigeoController con `/api/v1/ubigeo/departments|provinces/{id}|districts/{id}`) + frontend `UbigeoSelect.vue` (3 selects encadenados, guarda los nombres) integrado en Clientes. Datos del Perú (joseluisq/ubigeos-peru) que se descargan una vez a `backend/resources/ubigeo/` (ver README ahí). Funciona offline en LAN. **Nota:** el autocompletado por RUC escribe los nombres pero el reflejo visual en los selects tras el autofill es una mejora menor pendiente (los datos se guardan bien).

**IGV incluido (APLICADO 2026-07-12):** los precios ingresados **incluyen IGV**. En `SaleService` la suma de líneas es el TOTAL a pagar; la base (op. gravada) e IGV se calculan hacia atrás con la tasa configurable (`base = total/(1+tasa)`, `igv = total − base`). La vista de Ventas refleja "Op. Gravada / IGV (18%) / Total a pagar" con la nota "Precios con IGV incluido". El comprobante hereda estos totales del sale (snapshot), así que boletas/facturas y su XML quedan coherentes. Ventas históricas conservan sus totales previos (no retroactivo, sin pérdida de datos). *(Compras no se tocó: es lado proveedor, decisión aparte.)*

- **Modelo/Unidad (ejecutado):** quitado "Año fabricación" de Unidad (columna conservada, no destructivo); Color de la unidad ahora se elige de los colores del modelo; precio de venta se prellena con el referencial del modelo al elegirlo. Búsquedas ampliadas: Unidades por VIN/código/motor/chasis/serie/color/modelo/versión/marca; Repuestos por descripción/código/código de barras/marca/categoría.

## Comprobantes: impresión A4/ticket + XML (2026-07-12)

- **Backend:** `InvoiceService` enriquece el detalle (`get`) con **ítems** (de la venta), **dirección del cliente**, **datos de empresa** (Settings: `company.*`) e `igvRate`. Nuevo endpoint `GET /api/v1/invoicing/documents/{id}/xml` que descarga el XML con nombre estándar `RUC-TIPO-SERIE-CORRELATIVO.xml`.
- **Frontend:** `utils/comprobante.ts` (número a letras en español + generación de HTML aislado e impresión en **A4** y **ticket 80mm** vía ventana nueva) + `invoicingService.downloadXml`. Botones "Imprimir A4", "Imprimir ticket" y "Descargar XML" en el detalle del comprobante. Muestra empresa, cliente, ítems, IGV, total en letras, hash y QR; aviso "SIN VALIDEZ TRIBUTARIA" mientras el proveedor sea simulado.
- **Pendiente (opcional):** el QR se imprime como su cadena de datos, no como imagen escaneable — requiere una librería pequeña de QR (decisión de dependencia del propietario).

## Rediseño Dashboard (prioridad 3 · 2026-07-12)

Decisión del propietario: mostrar datos también en gráficos, **máximo 2 gráficos** (evitar saturación). Implementados en **SVG/CSS puro, sin librerías nuevas** (respeta "no agregar dependencias").

- **Gráfico 1 — Tendencia de ventas (6 meses):** barras. Requirió agregar la serie mensual en `DashboardService::salesTrend()` (consulta agrupada por mes, rellena meses sin ventas con 0; se expone en `sales.trend`). **Sin migración** (solo consulta).
- **Gráfico 2 — Cobranza acumulada:** barra de progreso % cobrado + desglose facturado/cobrado/por cobrar (usa datos ya existentes).
- Rankings (vendedor, repuestos, clientes) convertidos a **barras de progreso** dentro de sus tarjetas (estilo de lista, no cuentan como gráfico).
- Reestilizado a tokens del Design System (slate/primary/emerald/accent), KPIs con jerarquía, alertas con icono, actividad reciente más limpia.
- Solo Dashboard; sin cambios de negocio ni de esquema. Puesta al día: el cambio de PHP lo toma `symfony server` en caliente y el frontend recarga por HMR (no requiere migración ni comandos especiales).

Patrón de gráficos reutilizable para Reportes cuando se aborden (mismo enfoque SVG/CSS, ≤ 2 por vista).

---

## Parte Comercial — filtros y flujo de Ventas (2026-07-12)

Evaluación de filtros de la sección Comercial y ajuste al flujo real de la tienda.

- **Ventas — flujo real:** se usa **venta directa** y **cotización**; la **reserva NO** se maneja en el sistema. La cotización ahora se **Aprueba** (→ venta completada, sigue con boleta/factura) o se **Rechaza** (→ anulada). Se quitó "Convertir a reserva" de la interfaz (el estado RESERVA se conserva en el enum del backend por compatibilidad, oculto en la UI — no destructivo). Nuevo botón **"Imprimir cotización"** (copia A4 para el cliente, reutiliza `utils/comprobante.ts` → `printCotizacion`). El detalle de venta ahora incluye datos de empresa/dirección/IGV para esa impresión.
- **Filtros más ordenados:** en **Ventas** y **Comprobantes** la fila de chips de estado se reemplazó por un **desplegable compacto** en la barra de la tabla (menos ruido visual, consistente). Ventas: Todos / Cotizaciones / Ventas completadas / Anuladas-rechazadas (sin Reserva). Comprobantes: Todos / Pendientes / Aceptados / Rechazados.
- **Clientes, Proveedores, Promociones:** ya tenían solo búsqueda (limpios); pendiente opcional un filtro Activos/Inactivos-Vigentes si el propietario lo desea (requiere ajuste del listado en backend).

Sin migraciones. Cambio de reglas de negocio (flujo de cotización) dirigido por el propietario.

- **Comprobantes — estado PENDIENTE sin SUNAT real:** `ProviderResult` ahora lleva `status` (ACEPTADO/RECHAZADO/PENDIENTE) en vez de un bool `accepted`; `applyProviderResult(string $status, …)`. El `SimulatedProvider` devuelve **PENDIENTE** (sin CDR): al no haber SUNAT real que evalúe, es más honesto que marcar "aceptado". Refleja la realidad (las boletas se aceptan por resumen diario). La impresión muestra "COMPROBANTE PENDIENTE — aún no validado por SUNAT". Cuando se conecte NubeFact, su adaptador devolverá el `status` real. Comprobantes ya emitidos conservan su estado previo (no retroactivo).

- **Promociones — simplificadas (a pedido del propietario):** de "algo simple y opcional". La gestión (`PromotionsView`) ahora solo tiene **código, nombre, descuento %, vigencia y activa** (se retiraron de la UI el tipo, el alcance por marca/categoría/modelo y las bonificaciones). En **Ventas** se quitó el auto-aplicado por alcance y las bonificaciones; ahora hay un selector **"Promoción (opcional)"** que aplica el % a todas las líneas (ajustable por línea después). El backend conserva el modelo completo (columnas de alcance/bonus) sin usar — no destructivo; la UI envía `type=DISCOUNT`, `scope=ALL`. El endpoint `/promotions/applicable` y `PromotionResolver` quedan sin uso (candidatos a limpieza en consolidación).

## Integración de APIs externas — APISPERU (DNI/RUC) · Infraestructura (2026-07-12)

Prioridad 4. Etapa de preparación validada con el propietario: APISPERU usa **un único token JWT** (no API Key + token); base `https://dniruc.apisperu.com/api/v1`; solo producción; plan gratuito 2000/mes. RUC no entrega actividad económica/CIIU (queda `null` en el DTO).

**Arquitectura desacoplada** (mapea el diseño del propietario a la convención `src/Module`): `src/Module/Lookup/`
- `Provider/DocumentLookupProviderInterface` — el ERP depende SOLO de esta interfaz. Cambiar a RENIEC/SUNAT = nuevo adaptador + cambiar el alias (services.yaml), sin tocar el resto.
- `Infrastructure/ApisPeru/ApisPeruClient` (implementa la interfaz con **cURL nativo, sin dependencias nuevas**) + `ApisPeruConfig` (lee token/base/timeout SOLO de env).
- `Dto/PersonResult`, `Dto/CompanyResult` (normalizados, agnósticos del proveedor).
- `Exception/` — jerarquía: `LookupException` base + `InvalidDocument`, `DocumentNotFound`, `LookupAuth`, `LookupRateLimit`, `LookupUnavailable`, `InvalidLookupResponse`. Cubre conexión, timeout, credenciales, límite de uso, no encontrado y respuesta inválida.
- `Service/PersonLookupService`, `Service/CompanyLookupService` (validan formato 8/11 dígitos, delegan en la interfaz, registran fallos técnicos con el logger). Ningún controlador consume APISPERU directo.
- `Controller/LookupController` — `GET /api/v1/lookup/dni/{dni}` y `/ruc/{ruc}`; solo traduce excepciones a HTTP (422/404/429/503/502). Requiere autenticación (firewall).
- `services.yaml`: alias `DocumentLookupProviderInterface → ApisPeruClient`.

**Seguridad de credenciales:** el token NUNCA está en el código. `.env` (versionado) solo lleva valores no secretos (`APISPERU_BASE_URL`, `APISPERU_TIMEOUT`) y `APISPERU_TOKEN=` **vacío**. El token real va en `backend/.env.local` (no versionado). Verificado: el token no aparece en ningún archivo del repo.

### Incidencia y resolución — "No se pudo conectar" (2026-07-12)

**Síntoma:** las consultas DNI/RUC fallaban con `LookupUnavailableException`; el log no daba detalle.

**Diagnóstico (comando `app:lookup:test`, proceso CLI fresco sin opcache):**
1. Primero apareció un `TypeError` en `ApisPeruConfig`: el env `%env(default::APISPERU_CAINFO)%` devuelve **null** cuando la variable está vacía, y el parámetro era `string` no-nulable. Bug introducido al añadir el soporte de CA. **Fix:** usar `%env(APISPERU_CAINFO)%` directo (definido vacío en `.env`).
2. Resuelto eso, el comando mostró la **causa raíz real**: `cURL 60 — SSL certificate: unable to get local issuer certificate`. PHP en Windows no tiene CA bundle configurado (`curl.cainfo` vacío) y la verificación TLS estricta corta la conexión. Token, URL y entorno estaban correctos (confirmado por el snapshot del comando).

**Solución:** configurar el CA bundle (`cacert.pem` de https://curl.se/ca/cacert.pem) en `php.ini` (`curl.cainfo` + `openssl.cafile`) — arregla APISPERU y toda futura llamada HTTPS (SUNAT, facturación). Alternativa portable: `APISPERU_CAINFO` en `.env.local`.

**Herramienta añadida:** `app:lookup:test` (comando de diagnóstico CLI: imprime config con token enmascarado + resultado real; útil para futuras integraciones). El cliente ahora **registra** errno+curlError en el log (observabilidad), y el mensaje de cara al usuario volvió al texto limpio tras confirmar la causa.

**Estado:** ✅ RESUELTO y validado — DNI autocompleta nombre; RUC autocompleta razón social + dirección/ubigeo.

**Autocompletado (frontend) — CONECTADO:** `services/lookup.ts` + `types/lookup.ts`. En **Clientes**, botón "Buscar" junto al número de documento: si el tipo es DNI rellena Nombres y Apellidos; si es RUC rellena Razón Social, Nombre Comercial, Dirección, Distrito, Provincia y Departamento. En **Proveedores**, botón "Buscar" junto al RUC rellena Razón Social, Nombre Comercial, Dirección y Ciudad. Feedback con Toast (éxito y errores mapeados: no encontrado, formato inválido, servicio no disponible, límite). El frontend nunca habla con APISPERU: pasa por `/api/v1/lookup/*` del backend desacoplado.

### Carga masiva de productos/repuestos por Excel/CSV (2026-08-02)

**Pedido del cliente:** subir muchos productos de golpe "como en un Excel", **compatible con lo ya cargado** (que no duplique). Decisiones tomadas con el cliente: (1) si el código ya existe → **actualizar** sus datos (incluido precio); si no → crear (upsert); (2) **stock inicial solo en productos nuevos** (genera movimiento AJUSTE en el Kardex); en existentes el stock no se toca.

**Implementación (sin dependencias nuevas — CSV nativo de PHP, compatible con Excel):**
- `Inventory/Service/SparePartImportService`: genera la **plantilla CSV** (UTF-8 con BOM, delimitador `;` para Excel en español, fila de ejemplo REP-001) y procesa la subida. Autodetecta delimitador (`;`, `,`, tab), normaliza cabeceras (tolera acentos/espacios/variantes vía aliases), parsea decimales en formato peruano (`1.234,50` / `1,234.50` / `18.00`). Upsert por código interno o código de repuesto, con detección de **conflicto de códigos cruzados** (pertenecen a productos distintos → fila en error). Marca/Categoría se resuelven por nombre y se **crean al vuelo** (`CatalogService::findOrCreateByName`, nuevo). Registra historial de precios (A3) en altas/cambios y stock inicial vía `StockService` (AJUSTE).
- Flujo **preview → confirmar**: `POST /api/v1/inventory/spare-parts/import?dryRun=1` devuelve el resumen (crear/actualizar/error por fila) **sin guardar nada**; con `dryRun=0` aplica. `GET .../import/template` descarga la plantilla. Permiso `inventory.spare_parts.create`. Validación por fila con try/catch: una fila mala no aborta el lote.
- Frontend: botón **"Importar Excel"** en la toolbar de Repuestos + modal (descargar plantilla, subir archivo, tabla de vista previa con estados coloreados, botón Confirmar con el conteo). `services/inventory.ts`: `downloadImportTemplate`, `import(file, dryRun)` + tipos `ImportResult/ImportRow`.

**Columnas de la plantilla:** Código Interno* · Código de Repuesto* · Descripción* · Marca · Categoría · Unidad de Medida · Stock Mínimo · Precio de Compra · Precio de Venta (IGV incl.) · Ubicación · Stock Inicial (solo nuevos) · Activo (SI/NO).

**Verificación:** lógica de delimitador/cabeceras/decimales validada con simulación (todos los formatos peruanos OK). Typecheck del frontend no ejecutable en este entorno (node_modules incompletos en el montaje); revisión manual del SFC y servicio.

**Pendiente (mejoras futuras):** columna de modelos compatibles en la plantilla (v1 se asignan editando el repuesto); soporte de .xlsx nativo (hoy .csv, que Excel abre/guarda de forma nativa) si se decide añadir PhpSpreadsheet.

### Carga masiva extendida a Clientes y Unidades de moto (2026-08-02)

Se aplicó el mismo patrón de importación (plantilla → vista previa → confirmar) a **Clientes** y **Unidades de moto**, extrayendo la lógica común a un lector CSV compartido.

- `Shared/Import/CsvImportReader` (nuevo, reutilizable): genera plantillas (UTF-8/BOM, `;`) y lee archivos autodetectando delimitador, normalizando cabeceras (aliases tolerantes a acentos/espacios) y parseando decimales/enteros/booleanos en formato peruano.
- `Customer/Service/CustomerImportService` + endpoints `GET/POST /api/v1/customers/import[/template]` (permiso `customers.list.create`). **Upsert por (tipo + número de documento)**. Si el tipo va vacío se **infiere** del número (8 díg. → DNI, 11 díg. `10/15/17/20` → RUC). Réplica de la validación de formato de documento (§7). Columnas: tipo/número doc, nombre/razón social, nombre comercial, dirección, distrito/provincia/departamento, teléfono, email, activo.
- `Motorcycle/Service/UnitImportService` + endpoints `GET/POST /api/v1/motorcycles/units/import[/template]` (permiso `motorcycles.units.create`). **Upsert por VIN** (valida 17 caracteres, sin I/O/Q). El **modelo** se referencia por su nombre completo (matching normalizado, tolerante a espacios). Valida nº de motor duplicado y **bloquea modificar motos VENDIDAS**. Proveedor por RUC (opcional). Cada fila es una moto física (sin concepto de "stock inicial").
- Frontend: componente reutilizable `components/ui/ImportModal.vue` (descarga plantilla, sube, vista previa con estados, confirmar) + tipos `types/import.ts` + helpers `services/importHelpers.ts`. Botón "Importar Excel" en **Clientes** y **Unidades**. Repuestos mantiene su modal propio (equivalente).

**Verificación:** simulación en Python de mapeo de cabeceras, inferencia de tipo de documento, validación de formato, validación de VIN y matching de modelo — todo OK. Typecheck no ejecutable en el entorno (node_modules incompletos en el montaje); revisión manual de SFC/servicios.

### Verificación de la carga masiva (2026-08-02)

Al no haber PHP/Composer en el entorno de trabajo, se validó mediante: (1) auditoría estática del cableado (rutas únicas, autowiring que cubre `Shared/Import`, firmas de setters/repositorios/servicios, imports de controladores, props/emits del frontend) y (2) simulación fiel del algoritmo en Python con CSVs realistas (altas, actualizaciones, filas en blanco, formatos peruanos de decimal, inferencia de tipo de documento, validación de VIN, matching de modelo).

**Hallazgo y corrección:** la simulación detectó que la **vista previa** no coincidía con el **guardado** ante duplicados dentro del MISMO archivo (p. ej. dos motos con el mismo nº de motor y distinto VIN): el preview no persiste, así que no los veía. Se añadió detección de duplicados intra-archivo en los tres importadores (Repuestos: código interno/repuesto; Clientes: tipo+número de documento; Unidades: VIN y nº de motor). Ahora preview y commit producen conteos idénticos y el nº de motor repetido en el archivo se marca como error en ambas pasadas. Reverificado con simulación: preview == commit en los tres.

### SUNAT · Fase 1 — Adaptador NubeFact pre-construido (2026-08-02)

Decidido con el cliente pre-construir la integración ahora (listo para enchufar credenciales DEMO). Alcance priorizado: Boletas/Facturas, boletas simples, NC/ND y Guía de Remisión. Esta fase entrega la BASE (boleta/factura):

- `NubefactConfig` (env-only) + `NubefactProvider` (cURL nativo) implementando `ElectronicInvoiceProviderInterface`. Arma el JSON de NubeFact, envía, y mapea la respuesta a `ProviderResult` (estado real + hash + QR + enlaces PDF/XML/CDR). Precios con IGV incluido: base/IGV por línea calculados hacia atrás y **sumados** para reconciliar con el encabezado (verificado por simulación: Σbase+Σigv = total).
- `ProviderResult` y `ElectronicDocument` extendidos con `pdfUrl/xmlUrl/cdrUrl`; migración `Version20260802120000` (ALTER ADD 3 columnas). `InvoiceService` los persiste y expone; `InvoicesView` muestra los enlaces oficiales cuando existen.
- Variables `NUBEFACT_*` en `.env` (vacías) y `.env.local` para los secretos. **Alias sigue en Simulated**; el cambio a NubeFact es una línea en `services.yaml` tras validar en DEMO.

**Verificación:** balance de llaves/paréntesis OK en los 4 archivos PHP; simulación numérica de reconciliación de totales y del mapeo de tipos correcta. No ejecutable de extremo a extremo sin cuenta NubeFact (por diseño: se valida en DEMO). **Bloqueo para activar:** ruta + token + series del ambiente DEMO.

### Boleta simple · Cliente "Público General" (2026-08-03)

Pedido del cliente: emitir boletas de venta de productos sin obligar a ingresar DNI/RUC.

- Cliente genérico **"Público General"** (documento reservado `00000000`, tipo OTRO). `Customer::GENERIC_DOC_NUMBER`. `CustomerService::ensureGeneric()` lo busca/crea; endpoint `GET /api/v1/customers/generic` (permiso `customers.list.view`).
- Frontend: en **Ventas → Nueva venta/cotización**, botón **"Público general"** junto al selector de cliente que lo elige al instante (sin pedir datos). El genérico se garantiza al inicio de la lista.
- Regla SUNAT en `InvoiceService::issueForSale`: la boleta a "Público General" (sin identificación) solo se permite **hasta S/ 700**; por encima exige registrar al cliente con DNI. Al emitir por NubeFact, el tipo de documento OTRO mapea a "-" (sin documento) con número `00000000`, que es como SUNAT acepta la boleta sin datos.
- Sin cambios de esquema (el genérico se crea on-demand reutilizando la entidad Customer). Verificado: balance de llaves/etiquetas OK.

### Ventas · Alta rápida de cliente + buscador escrito en líneas (2026-08-03)

- **Alta rápida de cliente** desde la venta: botón "+ Cliente" junto al selector abre un modal compacto (tipo/número de documento con botón Buscar DNI/RUC, nombre, teléfono, correo, dirección). Al crear, usa el endpoint existente `POST /customers`, agrega el cliente a la lista y lo selecciona en la venta en curso, sin cerrarla. Reutiliza `lookupService` (APISPERU).
- **Buscador escrito (typeahead)**: nuevo componente reutilizable `components/ui/SearchableSelect.vue` (input con filtrado en vivo, selección por click, cierre con retardo en blur). Reemplaza los `<select>` largos de Repuesto y Unidad en el detalle de la venta: ahora se escribe código/nombre/modelo/color para filtrar. Emite el id y dispara `onLineProductChange` igual que antes (prellenado de precio intacto). Servicio sigue como texto libre.
- Ambos son solo frontend (sin cambios de backend). Verificado: balance de etiquetas/llaves OK; tipos `SaleLine.sparePartId/motorcycleUnitId` (number|null) compatibles con el componente.
