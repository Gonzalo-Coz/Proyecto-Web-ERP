# Requisitos Funcionales y No Funcionales — ERP YIGM / Global Motors

> Compilado a partir del sistema implementado (módulos, flujos y reglas de negocio vigentes).
> Dealer Yamaha en Tingo María (zona Amazonía, IGV exonerado por defecto). Stack: Symfony + PostgreSQL (backend) y Vue 3 + Vite (frontend), desplegado en Railway; e-facturación vía NubeFact.

---

## 1. Requisitos Funcionales (RF)

### Seguridad y accesos
- **RF-01** El sistema debe autenticar a los usuarios con usuario/contraseña y emitir un token JWT para la sesión.
- **RF-02** El acceso a cada módulo y acción debe controlarse por **roles y permisos** (ej. `sales.list.view`, `invoicing.documents.create`, `reports.main.view`).
- **RF-03** Debe permitir administrar usuarios y roles (crear, editar, activar/desactivar, asignar permisos).

### Clientes y proveedores
- **RF-04** Registrar y editar clientes (persona con DNI o empresa con RUC) con dirección, ubigeo, teléfono y correo.
- **RF-05** Autocompletar datos del cliente por **DNI/RUC** consultando un proveedor externo (Decolecta), incluyendo dirección y ubigeo cuando el proveedor lo entregue.
- **RF-06** Registrar y editar proveedores (RUC, razón social, contacto).

### Motocicletas e inventario de motos
- **RF-07** Gestionar **modelos** de motocicleta (marca, modelo, versión, año) y **unidades** (VIN, color, N° de motor, DUA, precios, estado).
- **RF-08** El inventario de motos maneja hoy **6 estados** (DISPONIBLE, RESERVADA, VENDIDA, EN_TALLER, GARANTIA, BAJA). El stock refleja solo las disponibles. *(Pendiente: reducir a DISPONIBLE/RESERVADA/VENDIDA/BAJA, ya que el taller se desacopló del inventario.)*
- **RF-09** Los precios de la unidad se almacenan en **soles**; las importaciones en US$ se convierten con el tipo de cambio al registrarse.

### Repuestos e inventario
- **RF-10** Gestionar repuestos con código interno, código Yamaha, descripción, **marca y categoría (kits)**, stock, costo y precio de venta, ubicación.
- **RF-11** Todo cambio de stock debe pasar por un **único punto (Kardex)** con movimientos COMPRA, VENTA, TALLER, AJUSTE, DEVOLUCIÓN, de forma transaccional y sin stock negativo.
- **RF-12** Permitir **ajuste manual** de stock con motivo (queda en el Kardex).
- **RF-13** Buscar repuestos por código, descripción, **categoría/kit**, marca o código de barras, y filtrar por categoría.

### Compras
- **RF-14** Registrar compras a proveedor (manual) que incrementan stock y generan el Kardex correspondiente.
- **RF-15** **Importar la compra desde el XML** de la factura, con detección de DUA por VIN; los precios de las unidades se guardan en soles.
- **RF-16** Anular una compra revirtiendo el stock (bloquea si la mercadería ya se vendió/consumió).

### Ventas
- **RF-17** Registrar cotización, reserva y venta; completar una venta descuenta stock/marca la unidad como vendida.
- **RF-18** Diferenciar el **canal** de venta: **MOSTRADOR** (cliente final) y **TALLER**.
- **RF-19** Soportar descuentos por línea y globales (con autorización), multimoneda (PEN/US$ con T.C.) e **IGV exonerado** (Amazonía) o gravado (Local).
- **RF-20** Editar o anular una venta antes de que su comprobante sea aceptado por SUNAT, revirtiendo stock.
- **RF-21** Generar proforma/cotización imprimible (PDF) sin validez tributaria.

### Comprobantes electrónicos (NubeFact / SUNAT)
- **RF-22** Emitir **boletas y facturas electrónicas** a SUNAT vía NubeFact, usando la fecha de emisión del día.
- **RF-23** Consultar, reenviar y sincronizar el estado real del comprobante con NubeFact.
- **RF-24** Gestionar **notas de crédito**: listarlas (reporte) e importar/consultar por número desde NubeFact, con su documento relacionado.
- **RF-25** Imprimir/descargar el comprobante (A4 y ticket) y acceder a PDF/XML/CDR.

### Guías de remisión (GRE)
- **RF-26** Crear, editar y anular guías de remisión, con destinatario, transportista (por RUC), punto de partida/llegada e ítems.
- **RF-27** Generar la guía a partir de un comprobante de venta (jala los ítems; el destinatario se ingresa aparte).
- **RF-28** Imprimir la guía en PDF con el diseño de la empresa.

### Taller (módulo independiente del inventario)
- **RF-29** Recepción de motocicleta (**express**): datos esenciales (cliente, moto/placa, kilometraje, motivo) y detalles opcionales plegables.
- **RF-30** Consulta vehicular **por placa** (Factiliza) que autocompleta marca, modelo, color y serie.
- **RF-31** Flujo en **3 etapas**: Recibida → En trabajo → Entregada (+ Anulada), con avance de un clic.
- **RF-32** Cargar trabajos (mano de obra) y repuestos; cargar **planes de mantenimiento** por modelo/kilometraje; editar precios y cantidades.
- **RF-33** **Facturar y entregar** en un solo paso; el comprobante detalla cada repuesto (con código) y la mano de obra.
- **RF-34** Generar e imprimir **Orden/Acta** e **Historia clínica** de la moto.
- **RF-35** El taller **no altera** el estado de inventario de la moto (módulo independiente, sin duplicidad).

### Caja
- **RF-36** Apertura/cierre de caja y registro de movimientos (ingresos/egresos), con cuadre diario.

### Dashboard y reportes
- **RF-37** Dashboard general (ventas, caja, inventario, taller, alertas) y **dashboards por área: Taller, Repuestos y Motos** con sus KPIs y gráficos.
- **RF-38** Reportes con rango de fechas: Ventas, Venta de Repuestos (formato Yamaha), Ventas Internas Motos, Stock de Repuestos, Stock y Ventas de Motos, Compras, Utilidades, Kardex, Comprobantes, Clientes, Proveedores, Auditoría.
- **RF-39** Exportar reportes a **Excel** (formato oficial Yamaha donde aplica) y el reporte de notas de crédito al formato del Registro de Ventas.
- **RF-40** Reportes de gestión por rango de fechas libre. *(Pendiente: preset de "quincena" para la presentación quincenal.)*

### Catálogos y precios
- **RF-41** Administrar catálogos (marcas, categorías, métodos de pago, bancos).
- **RF-42** Listas de precios e **historial de cambios de precio** con motivo.

---

## 2. Requisitos No Funcionales (RNF)

### Seguridad
- **RNF-01** Autenticación por JWT y autorización por permisos en cada endpoint; las contraseñas se almacenan con hash.
- **RNF-02** Las credenciales de terceros (NubeFact, Decolecta, Factiliza, SUNAT GRE) se guardan **solo en variables de entorno** (Railway), nunca en el código ni en el repositorio.
- **RNF-03** Cumplimiento de la **Ley 29733** (protección de datos personales): los datos personales (dirección, teléfono, titular de vehículo) se almacenan con el consentimiento del cliente y no se usan fuera de su fin.
- **RNF-04** Registro de **auditoría** de acciones sensibles (usuario, IP, módulo, acción).

### Integridad de datos
- **RNF-05** Las operaciones que afectan stock, caja o comprobantes deben ser **transaccionales** (todo o nada) y evitar duplicidad entre módulos.
- **RNF-06** El stock es siempre **trazable por Kardex**; no existe edición directa que lo altere por fuera.

### Tributario / normativo
- **RNF-07** La emisión electrónica debe cumplir las reglas de SUNAT (fecha de emisión del día, series/correlativos, tipos de comprobante) a través de NubeFact.
- **RNF-08** Soporte de **IGV exonerado (Amazonía)** por defecto y gravado cuando corresponda.

### Rendimiento y disponibilidad
- **RNF-09** Listados paginados e indexados en base de datos (índices por estado, fecha, código).
- **RNF-10** Despliegue en producción en **modo prod** (opcache, sin debug) para tiempos de respuesta adecuados. *(Nota: el Dockerfile fuerza `APP_ENV=prod`; conviene limpiar el `APP_ENV=dev` del `.env` para evitar ambigüedad.)*
- **RNF-11** Disponibilidad soportada por Railway; la base de datos y el backend en la misma región para minimizar latencia.

### Usabilidad
- **RNF-12** Interfaz en **español**, pensada para Tingo María (Amazonía), con flujos ágiles (recepción express, avance de 1 clic, autocompletado por DNI/RUC/placa).
- **RNF-13** Mensajes de error claros y accionables; los documentos imprimibles llevan el logo y formato de la empresa.

### Mantenibilidad y escalabilidad
- **RNF-14** Arquitectura **modular** por dominios; integraciones externas detrás de **interfaces intercambiables** (proveedor de DNI/RUC, de placa y de facturación se cambian por configuración, sin tocar el resto).
- **RNF-15** Migraciones de base de datos versionadas; cambios de esquema reproducibles y automáticos en el despliegue.
- **RNF-16** Código tipado (PHP tipado, TypeScript en el frontend) y verificable con herramientas de análisis.

### Seguridad operativa / respaldo
- **RNF-17** Versiones de dependencias mantenidas al día ante vulnerabilidades críticas (ej. parche de PHP).
- **RNF-18** Respaldo de la base de datos de producción (gestionado en Railway).

---

## 3. Notas de estado (verificado contra el código)

- **Implementado y en código, pendiente de desplegar** (`git push`): consulta vehicular por placa (RF-30), dashboards por área (RF-37), desacople del taller del inventario (RF-35) y guardado en soles al importar motos (RF-09/RF-15).
- **Pendiente (no implementado aún):** reducción de estados de moto a 4 (RF-08), preset de quincena en reportes (RF-40), KPI 2 (ingresos vs costo salarial del técnico, requiere cargar el costo del técnico), KPI 5 / catálogo web de repuestos.
- **Parcial:** notas de crédito (RF-24) permite importar/consultar; la **creación** desde el ERP está pendiente.

