# Documento de Adiciones — YIGM ERP

Según el Documento Maestro v1.0 (§24), las funcionalidades adicionales, mejoras evolutivas o nuevos requerimientos que surjan durante el ciclo de vida del ERP deben documentarse aquí, sin modificar la estructura base del Documento Maestro.

## Formato de registro

Cada adición debe incluir: número, fecha, solicitante, descripción, módulos afectados, estado (propuesta / aprobada / implementada) y fase en la que se implementa.

---

## Adiciones registradas

### Lote 1 — "Adiciones a documento MAESTRO V3.0" (recibido 2026-07-11) · Estado: APROBADO (2026-07-12) · en implementación incremental

**Progreso:** A1 ✅ · A2 ⛔ descartado · **A3 ✅ · A4 ✅ · A5 ✅ · A6 ✅ (2026-07-12)** — **Lote 1 COMPLETO**.

| # | Adición (sección) | Estado vs sistema actual | Plan |
|---|---|---|---|
| A1 | Descuentos en ventas (24.1) | ✅ **IMPLEMENTADO Y SIMPLIFICADO** por decisión del cliente 2026-07-11: descuento ÚNICAMENTE porcentual por línea de venta (admite decimales), recálculo automático, regla de no-negativo, descuento total en el snapshot del comprobante. El descuento global y por monto fijo fueron RETIRADOS a pedido del cliente | — |
| A2 | Autorización de descuentos (24.2) | ⛔ **DESCARTADO por el cliente 2026-07-11** ("un único comportamiento para todos los usuarios"). Las columnas `roles.max_discount_percent` y `sales.discount_authorized_*` permanecen en BD sin uso, disponibles si se reactiva | — |
| A3 | Historial de precios (24.4) | ✅ **IMPLEMENTADO 2026-07-12** | Servicio transversal `Shared/Pricing` (entidad `price_history` append-only + `PriceHistoryService`) que registra cada cambio de precio de venta con su **motivo** y usuario; alimentado desde Repuestos (precio de venta) y Modelos de moto (precio de referencia) sin duplicar lógica. Reporte de consulta `GET /api/v1/pricing/price-history` con filtros (tipo, fechas, búsqueda) y vista "Historial de Precios" (permiso `pricing.history.view`). Excluido de la auditoría genérica para no duplicar el registro |
| A4 | Listas de precios (24.5) | ✅ **IMPLEMENTADO 2026-07-12** | Módulo `Module/Pricing`: `price_lists` + `price_list_items` (precio por producto, polimórfico spare_part/motorcycle_model), lista predeterminada única, CRUD con permiso `pricing.price_lists.*` y vista de gestión. Cliente con `price_list_id` opcional. `PriceResolver` (punto único: lista del cliente → predeterminada → precio base) expuesto en `GET /pricing/resolve`; Ventas **prellena** el precio de la línea al elegir producto/cliente conservando el override manual. Sin romper la regla de Ventas (el backend sigue tomando `unitPrice` de la línea) |
| A5 | Promociones (24.3) | ✅ **IMPLEMENTADO 2026-07-12** | `Module/Promotion`: entidad `Promotion` (tipo DISCOUNT/BONUS, vigencia inicio/fin, alcance ALL/BRAND/CATEGORY/MODEL). `PromotionResolver` (punto único) determina el mejor % de descuento y las bonificaciones aplicables a un producto; `PromotionController` CRUD (permisos `sales.promotions.*`) + `GET /promotions/applicable`. Ventas **prellena automáticamente** el % de descuento por línea (reutiliza el cuadro porcentual de A1) y ofrece las **bonificaciones** para agregarlas como línea a S/ 0.00. Sin alterar la regla de negocio (el backend sigue tomando descuento/precio de la línea). Vista de gestión de promociones. *Bonificación de moto: se agrega la unidad manualmente (v1)* |
| A6 | Pasarelas de pago (24.8) | ✅ **IMPLEMENTADO 2026-07-12** | `Module/Payment`: `PaymentGatewayInterface` + `ManualGateway` (v1, alias en services.yaml igual que el proveedor SUNAT) + `GatewayResult`; entidad `payment_transactions` (medio, monto, moneda, estado PENDING/APPROVED/REJECTED/VOIDED, nº operación, gateway, respuesta JSON, vínculo suelto a venta, auditoría de quién registra/valida). `PaymentGatewayService` (registro por la pasarela activa + validación manual aprobar/rechazar); `PaymentTransactionController` (permisos `payments.gateway.view/create/validate`). Vista de gestión con registro y validación. **Registro estructurado + validación manual**; sin tocar Ventas/Caja. Adaptador de agregador (Izipay/Niubiz/Culqi/Mercado Pago) se conecta cambiando SOLO el alias |
| — | Pagos parciales/separaciones (24.6) | **YA IMPLEMENTADO** (decisión #4: CxC, adelantos en reserva, estado RESERVADA) | Solo falta liberación automática por vencimiento (P3, ahora requerido) |

**Inconsistencias detectadas y resolución propuesta:** (1) tabla de roles fija en 24.2 vs roles dinámicos §23.9 → límite como atributo del rol; (2) "mostrar descuento en boleta/factura" vs inmutabilidad del comprobante §19 → el snapshot del comprobante incorporará el detalle de descuentos AL EMITIRSE (no retroactivo); (3) 24.8 menciona moneda → depende de P2 (multimoneda), v1 opera en PEN; (4) 24.6 duplica §12 → sin conflicto, ya cubierto.

Orden de implementación propuesto: A1 → A2 → A3 → A4 → A5 → A6.
