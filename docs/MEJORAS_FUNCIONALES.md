# Fase de Evolución — Catálogo de Mejoras Funcionales

Propuestas de consultoría para la segunda fase del ERP YIGM. Ninguna elimina funcionalidad, contradice el Documento Maestro ni altera la arquitectura. Cada mejora indica su valor y esfuerzo (B=bajo, M=medio, A=alto).

Complementa (no reemplaza) el Lote 1 aprobado del Documento de Adiciones (A1–A6: descuentos, autorización, historial de precios, listas de precios, promociones, pasarelas).

## 1. Transversales y experiencia de uso

| # | Mejora | Valor | Esfuerzo |
|---|---|---|---|
| M-01 | **Búsqueda global (omnibox)**: un solo buscador en la cabecera que encuentra clientes por documento, unidades por VIN, ventas, órdenes y comprobantes por número | El personal deja de navegar módulo por módulo; es la mejora de productividad diaria más pedida | M |
| M-02 | **Notificaciones internas (campana)**: las alertas del Dashboard (stock, caja sin cerrar, comprobantes rechazados, órdenes retrasadas) visibles desde cualquier pantalla, con marcado de leído | Las alertas dejan de depender de visitar el Dashboard | M |
| M-03 | **Acciones rápidas** en el Dashboard: botones "Nueva venta", "Abrir caja", "Recepcionar moto" según permisos | Menos clics para las 3 operaciones más frecuentes | B |
| M-04 | **Preferencias por usuario**: filas por página, módulo de inicio tras login, densidad de tablas | Comodidad diaria; se guarda en BD por usuario | B |
| M-05 | **Exportación XLSX y PDF nativa** en todas las tablas y reportes (completa el P4) + **impresión de comprobantes** A4/ticket 58-80mm con diseño configurable (§15) | Requisito del Maestro pendiente de infraestructura; imprescindible para operar | A |

## 2. Seguridad y trazabilidad

| # | Mejora | Valor | Esfuerzo |
|---|---|---|---|
| M-06 | **Registro de accesos**: tabla de inicios de sesión (usuario, IP, fecha, éxito/fallo) + **bloqueo temporal tras N intentos fallidos** (N configurable) | Endurece el login y da evidencia ante incidentes; extiende §23.12/23.14 | M |
| M-07 | **Política de contraseñas**: longitud/complejidad mínima configurable + cambio obligatorio en el primer ingreso + caducidad opcional | Higiene básica que toda auditoría pide | B |
| M-08 | **Visor de auditoría** con diff visual (antes/después en colores), filtros por usuario/módulo/entidad y acceso desde cada registro ("ver historial de este cliente") | La auditoría ya se captura; esto la vuelve consultable por humanos | M |
| M-09 | **Último acceso visible** en la cabecera ("Tu última sesión: ...") y en la ficha de cada usuario | Detección temprana de accesos indebidos | B |

## 3. Clientes y ventas

| # | Mejora | Valor | Esfuerzo |
|---|---|---|---|
| M-10 | **Autocompletado RUC/DNI** al registrar clientes, consultando APIs públicas peruanas (SUNAT/RENIEC vía proveedor tipo apis.net.pe), con adaptador desacoplado como SUNAT/pasarelas | Elimina errores de digitación y acelera el alta; estándar de facto en software peruano | M |
| M-11 | **Ficha 360° del cliente**: pestañas con sus motos compradas, servicios, comprobantes, saldo CxC y línea de tiempo (materializa los historiales del §7 en una sola vista) | Vista comercial completa antes de atender | M |
| M-12 | **Límite de crédito por cliente**: monto máximo de saldo CxC; la venta al crédito se bloquea (o pide autorización, reutilizando A2) al excederlo | Control de riesgo indispensable si se vende al crédito | M |
| M-13 | **Comisiones por vendedor**: % configurable (global o por rol) + reporte mensual de comisiones calculado sobre ventas cobradas | Motiva al equipo y elimina el Excel paralelo | M |
| M-14 | **Cotización profesional**: vigencia configurable (settings), PDF con datos de empresa y envío por correo al cliente | La cotización es la herramienta de venta de motos; hoy solo vive en pantalla | M (tras M-05) |

## 4. Motocicletas e inventario

| # | Mejora | Valor | Esfuerzo |
|---|---|---|---|
| M-15 | **Línea de tiempo del expediente digital**: vista cronológica por unidad (compra→ingreso→reserva→venta→servicios), derivada de datos existentes | El expediente §9.2 completo en una vista vendible al cliente final | M |
| M-16 | **Antigüedad de stock (aging)**: alerta y reporte de unidades con más de X días sin venderse (X configurable) | Las motos paradas son capital inmovilizado; decisión típica de gerencia | B |
| M-17 | **Datos post-venta de la unidad**: placa, fecha de entrega física y contacto del propietario actual | Cierra el ciclo administrativo tras la venta (§15 menciona trámites) | B |
| M-18 | **Recordatorios de mantenimiento**: a los N días/km de la venta, lista de clientes a contactar (con enlace WhatsApp `wa.me` prellenado, sin API) | Genera retorno al taller: ingreso recurrente con costo casi nulo | M |
| M-19 | **Inventario físico**: hoja de conteo, captura de conteos y acta de ajuste masivo (genera los AJUSTE del Kardex en lote, con motivo) | Todo almacén real hace conteos; hoy serían ajustes uno a uno | M |
| M-20 | **Sugerencia de reposición**: pantalla "qué comprar" (stock ≤ mínimo) agrupada por proveedor principal, convertible en compra con un clic | Automatiza la decisión de reposición usando datos ya existentes | M |
| M-21 | **Etiquetas de código de barras** imprimibles para repuestos (el campo ya existe) | Habilita lector de barras en venta y conteo | B (tras M-05) |

## 5. Caja, compras y taller

| # | Mejora | Valor | Esfuerzo |
|---|---|---|---|
| M-22 | **Arqueo con denominaciones**: conteo de billetes/monedas en el cierre; el sistema suma y calcula la diferencia | Reduce errores del conteo manual; queda registrado en el cierre | B |
| M-23 | **Categorías de egresos** (catálogo nuevo type=expense_categories) en movimientos manuales de caja + reporte de gastos por categoría | Gerencia ve en qué se va el efectivo | B |
| M-24 | **Cuentas por pagar básicas**: fecha de vencimiento en facturas de compra + alerta de pagos próximos + registro de pagos a proveedor (egreso de caja vinculado) | Simetría con CxC ya implementado; evita moras | M |
| M-25 | **Checklist de recepción en taller**: estado del vehículo (tanque, espejos, rayones…) y accesorios dejados, impreso/confirmado con el cliente | Protege a la empresa de reclamos; práctica estándar de talleres | M |
| M-26 | **Aviso "moto lista"**: al pasar a LISTA_PARA_ENTREGA, enlace WhatsApp prellenado al celular del cliente | Comunicación inmediata sin integraciones pagadas | B |

## 6. Priorización recomendada (criterio valor/esfuerzo)

**Ola 1 (rápidas, alto impacto):** M-03, M-16, M-17, M-22, M-23, M-26, M-09, M-07.
**Ola 2 (productividad):** M-01, M-02, M-08, M-10, M-11, M-20.
**Ola 3 (requieren infraestructura PDF, M-05 primero):** M-05 → M-14, M-21 + M-19, M-24, M-12, M-13, M-25, M-18, M-04, M-06, M-15.

El Lote 1 aprobado (A1–A6) puede intercalarse: A1–A3 antes de la Ola 1 (ya aprobados), A4–A5 junto a la Ola 2, A6 cuando se contrate la pasarela.
