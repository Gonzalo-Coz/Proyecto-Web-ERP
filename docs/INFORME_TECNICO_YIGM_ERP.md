# INFORME TÉCNICO DE ANÁLISIS — ERP YIGM

**Proyecto:** ERP Yamaha Integral Global Motors (YIGM ERP)
**Base:** Documento Maestro de Desarrollo v1.0 (MASTER_PROMPT_YIGM_ERP_v3.docx)
**Fecha:** 10 de julio de 2026
**Estado:** Análisis previo al desarrollo — pendiente de aprobación

---

## 1. Comprensión general del proyecto

YIGM ERP es una plataforma web interna (red local, acceso por navegador) para gestionar de forma integrada los procesos de una empresa comercializadora de motocicletas Yamaha: ventas de motocicletas, repuestos y servicios, compras, inventario con Kardex, caja, taller, facturación electrónica SUNAT (Perú), reportes, dashboard gerencial, configuración y auditoría.

Características definitorias del negocio que condicionan el diseño:

- **Doble naturaleza del inventario:** las motocicletas se gestionan a dos niveles (Catálogo de Modelos + Unidad Física identificada por VIN, con expediente digital de por vida), mientras que los repuestos son stock cuantitativo con compatibilidades hacia múltiples modelos. Son dos submodelos de inventario distintos que comparten el Kardex.
- **Integración reactiva entre módulos:** toda operación propaga efectos automáticos (venta → inventario + caja + dashboard + historial cliente/moto + garantía; compra → inventario + Kardex + obligación de pago; orden de servicio → descuento de repuestos + expediente).
- **Cumplimiento tributario SUNAT:** boletas, facturas, notas de crédito/débito electrónicas con XML, firma digital, CDR, hash, QR; los comprobantes enviados son inmutables.
- **Trazabilidad total:** auditoría de acciones (valores anteriores/nuevos), historial de cambios en entidades críticas, soft delete, logs de sistema.
- **Alcance v1:** monoempresa, monosucursal, sin CxC/CxP formales ni contabilidad; pero arquitectura preparada para multisucursal, multialmacén, app móvil, portales, BI, etc.

Perfiles mínimos: Administrador, Gerencia, Supervisor, Vendedor, Cajero, Almacén, Mecánico — con roles/permisos dinámicos (módulo × pantalla × acción) administrables sin tocar código.

## 2. Validación de la arquitectura propuesta

La arquitectura cliente-servidor desacoplada (Nuxt 3/Vue 3 ↔ API REST JSON ↔ Symfony ↔ PostgreSQL, con integración SUNAT desde el backend) es **correcta y adecuada** para este tipo de sistema. Puntos validados:

- **Symfony + Doctrine + PostgreSQL** es una combinación madura para ERPs: transacciones ACID, migraciones, Repository Pattern nativo, eventos de dominio, seguridad robusta. PostgreSQL soporta bien las relaciones complejas (compatibilidades, Kardex, auditoría con JSONB para valores antes/después).
- **API REST + JWT** desacopla correctamente y habilita las integraciones futuras listadas (app móvil, portales).
- **Modularidad por dominio** (cada módulo con controllers, services, repositories, entities, DTOs, validators, events, listeners) es viable en Symfony organizando el código por *bounded context* dentro de un monolito modular. Para el alcance v1 (una empresa, red local), un **monolito modular es preferible a microservicios**: menor complejidad operativa y las transacciones críticas (venta que toca stock, caja, comprobante) se resuelven en una sola transacción de BD.
- **Eventos + listeners** para la propagación entre módulos (venta → caja/dashboard/kardex) es el mecanismo correcto para lograr integración sin acoplar servicios entre sí. Recomendación: ejecutar los efectos que afectan integridad (stock, caja, kardex) dentro de la misma transacción, y los efectos informativos (alertas, dashboard) como listeners post-commit.

Observaciones de coherencia interna del documento (detalladas en §8): la sección 3 fija **Nuxt 3** como frontend, pero la sección 23.3 describe una estructura Vue "pura" (Views, Router) y la conclusión menciona "Vue.js, TypeScript, Tailwind" sin citar Nuxt. Se asume **Nuxt 3 con TypeScript, Tailwind CSS y Pinia** (compatible con todo lo pedido, usando la estructura de carpetas de Nuxt en lugar de router manual), pero conviene confirmarlo.

## 3. Estructura recomendada del proyecto

Monorepo con backend y frontend separados:

```
yigm-erp/
├── backend/                      # Symfony (API REST)
│   ├── config/
│   ├── migrations/
│   ├── src/
│   │   ├── Shared/               # Núcleo transversal
│   │   │   ├── Auditing/         # Auditoría, historial, soft delete
│   │   │   ├── Security/         # JWT, voters, roles/permisos
│   │   │   ├── Api/              # Respuestas estándar, paginación, filtros
│   │   │   ├── Export/           # PDF / Excel genéricos
│   │   │   ├── Files/            # Adjuntos
│   │   │   └── Settings/         # Configuración centralizada en BD
│   │   └── Module/
│   │       ├── Customer/         # Clientes
│   │       ├── Supplier/         # Proveedores
│   │       ├── Motorcycle/       # Catálogo + unidades + expediente
│   │       ├── Inventory/        # Repuestos, stock, Kardex, compatibilidades
│   │       ├── Purchasing/       # Compras
│   │       ├── Sales/            # Cotización, reserva, venta
│   │       ├── CashRegister/     # Caja
│   │       ├── Workshop/         # Taller
│   │       ├── Invoicing/        # Facturación electrónica SUNAT
│   │       ├── Reporting/        # Reportes
│   │       └── Dashboard/        # Indicadores y alertas
│   │   └── (cada módulo): Controller/ Service/ Repository/ Entity/
│   │                      Dto/ Validator/ Event/ EventListener/ Security/
│   └── tests/
├── frontend/                     # Nuxt 3
│   ├── components/
│   │   ├── ui/                   # Base: DataTable, FormField, Modal, etc.
│   │   └── <modulo>/
│   ├── composables/              # useApi, useAuth, usePermissions, useExport...
│   ├── layouts/
│   ├── middleware/               # auth, permisos por ruta
│   ├── pages/<modulo>/
│   ├── services/                 # Clientes API por módulo
│   ├── stores/                   # Pinia por módulo
│   └── types/                    # Tipos TS espejo de los DTOs
├── docs/                         # OpenAPI, decisiones de arquitectura
└── docker/                       # (recomendado) entorno reproducible
```

Clave: el bloque **Shared/** implementa una vez las 14 funciones comunes a todos los módulos (CRUD, búsqueda, filtros, paginación, exportación PDF/Excel, adjuntos, historial, auditoría, estado activo/inactivo) y cada módulo las hereda; esto materializa el requisito de reutilización y de interfaz homogénea.

## 4. Organización del Backend y Frontend

**Backend (Symfony, Clean Architecture):**

- **Controller** → recibe request, valida DTO de entrada, delega al Service, devuelve DTO de salida. Sin lógica de negocio.
- **Service (capa de aplicación/dominio)** → reglas de negocio, orquestación transaccional, disparo de eventos de dominio (`SaleCompleted`, `PurchaseReceived`, `ServiceOrderClosed`...).
- **Repository (Doctrine)** → único punto de acceso a datos; consultas optimizadas con índices y paginación.
- **Events/Listeners** → integración entre módulos: `SaleCompleted` → listeners de Inventory (descuento y Kardex), CashRegister (movimiento), Motorcycle (estado + expediente), Invoicing (comprobante), Dashboard (estadísticas). Efectos de integridad dentro de la transacción; efectos informativos post-commit.
- **Security** → autenticación JWT (con refresh token) + Voters de Symfony que consultan la matriz rol-permiso (módulo/pantalla/acción) almacenada en BD.
- **Transversales**: trait/suscriptor Doctrine para soft delete, auditoría automática (listener onFlush que captura valores antes/después), historial de cambios en entidades críticas, logs (Monolog) canalizados por tipo de evento.
- **API**: versionada (`/api/v1/...`), respuestas y errores con formato uniforme, documentada con OpenAPI/Swagger generado desde atributos.

**Frontend (Nuxt 3 + TypeScript + Tailwind + Pinia):**

- **Pages** por módulo (routing de Nuxt), protegidas por middleware de autenticación y permisos.
- **Componentes base reutilizables**: `DataTable` (ordenamiento, paginación, filtros, exportación, impresión), `SearchBar`, `AdvancedFilters`, formularios con validación (espejo de las reglas del backend), `FileAttachments`, `AuditTrail`, `StatusBadge`. Con esto, cada módulo nuevo es en gran parte composición de componentes existentes.
- **Stores Pinia** por módulo + store de sesión/permisos (la UI oculta acciones no permitidas; el backend siempre revalida).
- **Services/composables** tipados por módulo consumiendo la API; tipos TS espejo de los DTOs del backend.
- SPA (`ssr: false`) es suficiente para un ERP interno; simplifica despliegue en red local.

## 5. Modelo general de la base de datos y relación entre módulos

Agrupación de entidades principales (~40 tablas núcleo):

**Seguridad y sistema:** `users`, `roles`, `permissions` (módulo/pantalla/acción), `role_permission`, `settings` (configuración en BD), `audit_logs` (usuario, fecha/hora, IP, módulo, tabla, registro, valores JSONB antes/después), `change_history`, `system_logs`, `attachments` (polimórfica).

**Maestros:** `customers` (tipo/número doc únicos, natural/jurídico), `suppliers` (RUC único), `brands`, `categories`, `payment_methods`, `banks`, `document_types`.

**Motocicletas:** `motorcycle_models` (catálogo técnico) → 1:N → `motorcycle_units` (VIN único, nro. motor, estado, precios, proveedor). El **expediente digital no es una tabla**: es la vista consolidada de ventas, servicios, garantías, repuestos, adjuntos y cambios de estado (`unit_status_history`) vinculados a la unidad.

**Repuestos e inventario:** `spare_parts` → N:M → `motorcycle_models` (tabla `spare_part_compatibility`, corazón del requisito de compatibilidades); `spare_part_stock` (actual/mín/máx, precios, ubicación — separado del maestro para facilitar multialmacén futuro); `kardex_movements` (tipo: compra/venta/taller/ajuste/devolución/transferencia; cantidad, costo, saldo, referencia polimórfica al documento origen).

**Compras:** `purchases` → `purchase_items` (referencia a repuesto o a unidad de moto), `purchase_documents` (facturas/NC de proveedor). FK a `suppliers`.

**Ventas:** `quotations` → `reservations` (opcional) → `sales` → `sale_items` (moto, repuesto o servicio); FK a `customers`, `users` (vendedor); `warranties` activadas por venta.

**Caja:** `cash_sessions` (apertura/cierre/arqueo, diferencias) → `cash_movements` (ingreso/egreso, medio de pago, referencia a venta u operación manual autorizada).

**Taller:** `service_orders` (cliente, unidad, kilometraje, estado, fechas) → `service_order_labor` (mano de obra) + `service_order_parts` (repuestos usados → Kardex) + `service_order_status_history`.

**Facturación:** `electronic_documents` (tipo, serie, correlativo, hash, QR, XML, CDR, firma, estado SUNAT — inmutables tras envío) ← FK desde `sales`; `document_series` (series y correlativos por tipo); `print_templates` (diseño editable).

**Relaciones inter-módulo esenciales:** Venta —descuenta→ Stock/Unidad —registra→ Kardex —genera→ Movimiento de caja —emite→ Comprobante —alimenta→ Dashboard y Expediente. Compra —incrementa→ Stock —registra→ Kardex. Orden de servicio —consume→ Repuestos —registra→ Kardex —forma parte de→ Expediente.

Convenciones: claves subrogadas (`id`), UNIQUE en documentos/VIN/motor/RUC, `deleted_at` para soft delete, `created_at/updated_at/created_by/updated_by` en todas las tablas, índices en columnas de búsqueda y FK, CHECK de stock ≥ 0 como defensa adicional a la validación de servicio.

## 6. Flujo general de funcionamiento del ERP

1. **Autenticación:** login → JWT + permisos → el frontend construye el menú según rol; toda petición se revalida en backend.
2. **Abastecimiento:** registro de compra (orden o directa) → recepción → transacción: incremento de stock (o alta de unidades de moto con VIN), Kardex, costo promedio, documento de proveedor → dashboard y alertas se actualizan.
3. **Ciclo comercial:** cotización → (reserva opcional, que bloquea la unidad/stock) → venta. Al confirmar la venta, en **una sola transacción**: validación de stock y estado de unidad, descuento de inventario + Kardex, cambio de estado de la moto, movimiento en caja (requiere caja abierta), emisión del comprobante electrónico (serie/correlativo), activación de garantía, historial de cliente y expediente. Post-commit: dashboard, alertas, envío a SUNAT (asíncrono con reintentos), correo al cliente.
4. **Taller:** recepción → diagnóstico → orden de servicio → asignación de mecánico → repuestos (descuento + Kardex al confirmar uso) y mano de obra → entrega → facturación del servicio → expediente digital.
5. **Caja:** apertura → movimientos automáticos (ventas) y manuales autorizados → cierre con arqueo y registro de diferencias → resumen diario.
6. **Transversal continuo:** auditoría de cada acción, historial de cambios, alertas (stock bajo/agotado, caja sin cerrar, comprobantes rechazados, garantías por vencer, órdenes retrasadas), reportes con filtros y exportación PDF/Excel, dashboard en tiempo real.

## 7. Plan de desarrollo por fases

El orden sigue las dependencias de datos: seguridad → maestros → inventario → compras (para poblar stock) → caja → ventas → facturación → taller → reportes/dashboard. Cada fase termina completamente funcional y probada antes de continuar.

| Fase | Contenido | Depende de |
|---|---|---|
| **0. Fundaciones** | Setup monorepo, Docker, CI básico, Symfony + Nuxt esqueleto, BD y migraciones iniciales, autenticación JWT, roles/permisos dinámicos, auditoría + soft delete + historial transversales, configuración centralizada, componentes UI base (DataTable, formularios, exportación PDF/Excel, adjuntos), layout general | — |
| **1. Maestros** | Clientes y Proveedores completos (validaciones de documento, historiales vacíos preparados), catálogos de Configuración (marcas, categorías, métodos de pago, bancos) | 0 |
| **2. Motocicletas** | Catálogo de modelos + unidades físicas (VIN, estados, expediente digital base) | 1 |
| **3. Repuestos e Inventario** | Repuestos, compatibilidades N:M, stock, Kardex, importación Excel, alertas de stock | 1–2 |
| **4. Compras** | Órdenes, compra directa, recepción, documentos de proveedor, devoluciones; integración stock/Kardex/costo promedio | 3 |
| **5. Caja** | Apertura/cierre/arqueo, medios de pago, movimientos manuales | 1 |
| **6. Ventas** | Cotización → reserva → venta (motos, repuestos, servicios), descuentos, integración transaccional con inventario + caja + expediente + garantías; comprobante **interno** provisional | 2–5 |
| **7. Facturación SUNAT** | Emisión electrónica (boleta, factura, NC, ND), XML/firma/CDR/QR, series y correlativos, estados SUNAT, diseño editable, formatos A4/58mm/80mm, reenvío por correo | 6 |
| **8. Taller** | Órdenes de servicio, estados, mecánicos, repuestos y mano de obra, integración con inventario/ventas/expediente | 3, 6 |
| **9. Reportes y Dashboard** | Los 13 reportes mínimos con exportación; dashboard gerencial completo, actividad reciente y alertas consolidadas | todas |
| **10. Cierre** | Auditoría de seguridad, pruebas de integración end-to-end, optimización (índices, consultas), copias de seguridad, documentación OpenAPI final, capacitación/despliegue en red local | todas |

Notas: el Dashboard se alimenta incrementalmente desde la fase 4 (cada módulo publica sus indicadores al terminar), pero se consolida en la fase 9. Separar Ventas (6) de SUNAT (7) reduce riesgo: el flujo comercial se valida internamente antes de introducir la complejidad tributaria. Las 14 funciones comunes se construyen una vez en fase 0 y se reutilizan siempre.

## 8. Riesgos técnicos, inconsistencias y aspectos a resolver antes de desarrollar

**Inconsistencias del documento (requieren confirmación):**

1. **Nuxt 3 vs Vue 3 "puro":** §3 exige Nuxt 3; §23.3 describe estructura con Views/Router (patrón Vue+Vue Router) y la conclusión omite Nuxt. Propuesta: Nuxt 3 (incluye Vue 3, routing por archivos, Pinia, TS y Tailwind). Confirmar.
2. **TypeScript y Tailwind** aparecen en §23.3 y la conclusión pero no en la tabla de tecnologías (§3). Se asumen incluidos. Confirmar.
3. **"Obligación de pago" en Compras (§11) y "Total cobrado" en Dashboard (§6)** implican cuentas por pagar/cobrar (crédito, cuotas, pagos parciales), pero no existe módulo CxC/CxP ni se define venta al crédito. Definir para v1: ¿solo contado, o pagos parciales/crédito? Impacta caja, ventas y dashboard.
4. **Regla "no vender sin stock" vs Reservas:** falta definir si la reserva bloquea stock/unidad y qué pasa al vencer. Propuesta: la reserva cambia la unidad a "Reservada" y compromete stock de repuestos, con vencimiento configurable.
5. **"No permitir stock negativo" vs Taller:** si un mecánico registra un repuesto sin stock, ¿se bloquea o queda "Esperando Repuestos"? Definir el flujo.
6. **Moneda:** compras registran "Moneda" pero no se define manejo de tipo de cambio ni si ventas pueden ser en USD. Definir: ¿v1 solo PEN, o PEN/USD con tipo de cambio?

**Definiciones faltantes críticas:**

7. **Método de integración SUNAT:** no se especifica si será emisión directa (SEE-SOL / SEE propio con certificado digital) o mediante OSE/PSE (NubeFact, APISUNAT, etc.). Es la decisión externa más importante del proyecto: afecta costos, plazos y complejidad (firma XML, certificados, homologación). Recomendación: OSE/PSE para v1 con capa de abstracción que permita cambiar de proveedor.
8. **Datos de precios/impuestos en venta:** el detalle de compra define IGV y descuentos, pero el de venta no especifica estructura (precios con/sin IGV, redondeos, descuentos por línea vs global). Definir antes de la fase 6.
9. **Valoración de inventario:** se menciona "costo promedio cuando corresponda"; confirmar promedio ponderado como método único de Kardex.
10. **Impresión de tickets (58/80 mm):** definir si se imprime vía PDF del navegador o impresoras térmicas ESC/POS directas (esto último requiere un agente local adicional).
11. **Infraestructura de despliegue:** servidor local sin especificar (SO, backups automáticos, HTTPS interno, tolerancia a cortes). Definir junto con la política de copias de seguridad exigida en Configuración.

**Riesgos técnicos:**

12. **Concurrencia en correlativos y stock:** ventas simultáneas pueden duplicar correlativos o sobrevender. Mitigación: bloqueos pesimistas/secuencias de BD para correlativos y verificación de stock con bloqueo dentro de la transacción.
13. **Disponibilidad de SUNAT:** la emisión no debe bloquear la venta. Mitigación: cola de envío asíncrona con reintentos y estados (pendiente/aceptado/rechazado) + alerta de rechazos, como ya prevé el documento.
14. **Volumen de auditoría:** registrar "todo" con valores antes/después crece rápido. Mitigación: JSONB indexado, particionado por fecha y política de archivado.
15. **Dashboard "tiempo real":** calcular indicadores en caliente sobre tablas transaccionales degradará el rendimiento con el tiempo. Mitigación: tablas de resumen actualizadas por eventos o vistas materializadas; definir si "tiempo real" admite refresco de segundos.

## 9. Recomendaciones técnicas (para evaluación, sin alterar el Documento Maestro)

1. **Monolito modular, no microservicios:** cumple la modularidad exigida (módulos independientes comunicados por eventos/interfaces) con transacciones simples y despliegue trivial en red local. Los módulos podrían extraerse a servicios en el futuro si multisucursal lo exigiera.
2. **Symfony 7 LTS + PHP 8.3, Nuxt 3 (Vue 3) + TypeScript + Tailwind, PostgreSQL 16:** versiones estables actuales alineadas con el stack definido; fijarlas al inicio para todo el proyecto.
3. **API Platform o controladores REST manuales:** recomiendo controladores + DTOs manuales para control total del formato de respuesta y de la lógica transaccional (API Platform acelera CRUD pero introduce "magia" que complica las operaciones compuestas del ERP). OpenAPI generado con NelmioApiDocBundle.
4. **JWT con refresh tokens** (LexikJWTAuthenticationBundle + refresh) y expiración configurable desde BD, cumpliendo el "tiempo de sesión" configurable.
5. **Abstracción del proveedor SUNAT** (interfaz `ElectronicInvoiceProviderInterface`): permite iniciar con un PSE y migrar a emisión directa sin tocar los módulos de negocio.
6. **Docker para desarrollo y despliegue local:** garantiza paridad de entornos y simplifica el servidor en red local (no altera el alcance; es infraestructura).
7. **Suite de pruebas por fase:** PHPUnit para servicios de negocio (reglas críticas: stock, correlativos, transacciones) y pruebas de API por módulo antes de cerrarlo; el documento ya exige "preparación para pruebas", propongo hacerlas obligatorias en reglas de negocio críticas.
8. **Tablas de resumen para el Dashboard** actualizadas por los mismos eventos de dominio, evitando consultas pesadas sobre transaccionales.
9. **Convenciones fijadas en fase 0:** inglés para código y BD con etiquetas/UI en español (o todo en español, a decisión del cliente), snake_case en BD, PSR-12 en PHP, ESLint/Prettier en frontend, ramas main/develop con commits convencionales, como pide §23.17.
10. **Documento de Adiciones desde el día 1:** el propio Documento Maestro lo establece; propongo crearlo en `docs/` y registrar allí las decisiones que resulten de las preguntas del §8.

---

**Próximo paso:** resolver los puntos 1–11 de la sección 8 (especialmente el método de integración SUNAT, el alcance de crédito/cobros y la confirmación de Nuxt 3 + TypeScript + Tailwind) y aprobar este informe. Con la aprobación, se iniciará la **Fase 0 — Fundaciones** sin generar código antes de dicha confirmación.
