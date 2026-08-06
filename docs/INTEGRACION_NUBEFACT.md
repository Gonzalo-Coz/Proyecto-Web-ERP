# Informe técnico — Integración Facturación Electrónica (NubeFact)

Análisis previo a la implementación. Fuente: documentación oficial de NubeFact (nubefact.com). **Aún no se implementa código**; este documento define requisitos, arquitectura y pasos.

---

## 1. Requisitos de NubeFact

NubeFact es **OSE y PSE autorizado por SUNAT**. Su integración por API usa **3 elementos**: una **ruta** (URL única de tu cuenta), un **token** de autenticación y un **JSON** con los datos del comprobante.

| Pregunta | Respuesta |
|---|---|
| ¿Plan gratuito? | Hay **registro/cuenta gratis** para integrar y probar en el **ambiente DEMO**. La **emisión real** (con validez SUNAT) requiere el plan de pago. |
| ¿Limitaciones del plan? | Plan "Integración vía TXT-JSON": **desde S/ 70.00/mes** (o S/ 700/año, IGV incluido), **hasta 500 comprobantes/mes** (costo por CPE adicional). Locales/usuarios sin límite. |
| ¿Emite comprobantes reales? | Sí, en **producción** (tras contratar el plan y conectar con SUNAT — 24 h). En DEMO solo son de prueba, sin validez. |
| ¿Sandbox/desarrollo? | **Sí**, ambiente **DEMO** para integrar y probar antes de producción. |
| ¿Incluye certificado digital? | **Sí, incluido.** No necesitas comprar ni gestionar un certificado aparte. |
| ¿Diferencia DEMO vs producción? | DEMO: comprobantes de prueba, sin validez tributaria, para desarrollo. Producción: emisión real validada por SUNAT. Cada ambiente tiene su **propia ruta (URL) y token**. |

> En resumen: **gratis para desarrollar/probar; ~S/70 al mes para emitir de verdad** (hasta 500 docs/mes), certificado incluido.

---

## 2. Datos que debes proporcionarme

Para la **API** de NubeFact NO se usan usuario/clave/código de empresa (eso es solo para el panel web). La integración necesita:

| Dato | Descripción | ¿De dónde sale? |
|---|---|---|
| **Ruta (URL)** | URL única de tu cuenta/establecimiento (ej. `https://api.nubefact.com/api/v1/xxxxxxxx`) | Panel NubeFact (una para DEMO, otra para producción) |
| **Token** | Token de autenticación de la API | Panel NubeFact (uno por ambiente) |
| **Ambiente** | `demo` o `produccion` | Lo defines tú según qué ruta/token uses |
| **Series** | Serie de Factura (ej. `F001`) y de Boleta (ej. `B001`) | Configuradas en NubeFact; deben coincidir con las del ERP |
| RUC de la empresa | Ya está en Configuración → General del ERP | Ya lo tenemos |

No asumo ningún valor: me los pasas tú tras crear la cuenta.

---

## 3. Configuración del ERP (variables de entorno)

Se agregarán a `backend/.env.local` (el token nunca en el código, igual que APISPERU):

```
NUBEFACT_URL=            # ruta (URL) de tu cuenta NubeFact
NUBEFACT_TOKEN=          # token de la API
NUBEFACT_AMBIENTE=demo   # demo | produccion
```

El RUC y las series **no** van en variables de entorno: ya viven en Configuración General y en la tabla `document_series` del ERP. (Nombres a validar contigo antes de escribirlos, como con APISPERU.)

---

## 4. Arquitectura (desacoplada, ya preparada)

**El ERP ya tiene exactamente el patrón que pides.** Desde la Fase 7 existe:

```
ElectronicInvoiceProviderInterface   (interfaz genérica; hoy con SimulatedProvider)
        ↑ implements
SimulatedProvider   (actual, de prueba)
```

La integración consiste en:

```
ElectronicInvoiceProviderInterface
        ↑ implements
NubefactProvider   (nuevo adaptador — encapsula TODA la comunicación con NubeFact)
```

- El módulo de **Ventas nunca conocerá NubeFact**: llama a `InvoiceService`, que depende de la **interfaz**, no del proveedor.
- Cambiar de proveedor (APISUNAT, Factiliza, otro) = crear otro adaptador que implemente la interfaz y **cambiar una línea** (el alias en `services.yaml`) — sin tocar Ventas.
- Ajuste necesario: extender `ProviderResult` para transportar los **enlaces de PDF/XML/CDR** que devuelve NubeFact (hoy solo lleva hash/qr/xml/cdr).

---

## 5. Flujo de emisión (usuario nunca sale del ERP)

```
Venta COMPLETADA
  → El usuario pulsa "Emitir comprobante" (elige Boleta/Factura)
  → InvoiceService asigna serie + correlativo (transaccional, con bloqueo)   [ya existe]
  → NubefactProvider arma el JSON y lo envía a la ruta de NubeFact (con el token)
  → NubeFact FIRMA el XML, lo ENVÍA a SUNAT y responde
  → Se guarda en el comprobante: estado (aceptado/rechazado), mensaje SUNAT,
     hash, cadena del QR, y los ENLACES de PDF / XML / CDR
  → El ERP muestra el resultado y permite Ver / Imprimir / Descargar
```

Todo dentro del ERP; NubeFact trabaja por detrás. La estructura del `InvoiceService` y del estado del comprobante **ya está construida** con el proveedor simulado; el adaptador real la activa.

---

## 6. Almacenamiento (respetando el Documento Maestro)

NubeFact **hospeda** el PDF, XML y CDR y devuelve **enlaces (URLs)**. Siguiendo el §Almacenamiento del Maestro ("solo rutas, nunca binarios en BD"):

- **Guardar los enlaces** (URLs de PDF/XML/CDR) en el comprobante → nuevos campos en `electronic_documents`.
- **XML, CDR, hash y QR**: ya hay columnas de texto; se llenan con la respuesta real.
- **Opcional (respaldo offline):** descargar y cachear XML/CDR/PDF en la infraestructura de archivos que ya existe (`ImageStorageService`/carpeta de uploads), para reimpresión sin depender de que NubeFact esté en línea. Recomendado para no perder acceso al comprobante.

---

## 7. Preparado para el futuro

La interfaz + el estado del comprobante permiten agregar sin rediseñar:

- **Descargar PDF / XML / CDR:** desde los enlaces guardados (o del caché local).
- **Reimpresión:** con nuestra plantilla A4/ticket ya construida, o el PDF oficial de NubeFact.
- **Envío por correo:** al cliente, con el PDF adjunto/enlace (se añade un método al servicio).
- **Consulta de estado:** NubeFact tiene la operación `consultar_comprobante` → método en la interfaz.
- **Anulación / comunicación de baja:** NubeFact soporta anulaciones → método en la interfaz.
- **Otros proveedores:** mismo contrato de interfaz, otro adaptador.

---

## 8. Qué debes hacer TÚ (antes de implementar)

1. **Crear cuenta gratis** en NubeFact: https://www.nubefact.com/registro
2. **Configurar los datos de tu empresa** en el panel (RUC, razón social, dirección, logo, colores).
3. Obtener del panel la **RUTA (URL)** y el **TOKEN** del **ambiente DEMO** (para pruebas).
4. **Configurar las series** en NubeFact (ej. `F001` factura, `B001` boleta) y confirmármelas (deben coincidir con las del ERP).
5. Para **producción** (emisión real):
   - **Contratar el plan** de Integración (~S/ 70/mes, hasta 500 docs/mes; certificado incluido).
   - **Autorizar a NubeFact como PSE/OSE en SUNAT** desde tu Clave SOL (NubeFact tiene manual paso a paso; conexión con SUNAT en 24 h).
   - Obtener la **ruta y token de producción**.
6. **Pasarme:** `NUBEFACT_URL`, `NUBEFACT_TOKEN`, `NUBEFACT_AMBIENTE` y las **series**.
7. Con eso: implemento el `NubefactProvider`, probamos de extremo a extremo en **DEMO**, validamos, y recién cambiamos el alias/ambiente a **producción**.

> El certificado digital lo incluye NubeFact — **no** compras uno aparte.

---

## Recomendación

Empieza por la **cuenta gratis + ambiente DEMO** para integrar y validar todo el flujo sin costo. Cuando confirmes que emite bien en pruebas, contratas el plan y autorizas en SUNAT para pasar a producción. No se escribe una línea de integración hasta que me entregues ruta + token (demo) y las series.

**Fuentes:** [NubeFact](https://www.nubefact.com/) · [Precios](https://www.nubefact.com/precios) · [Integración TXT/JSON](https://www.nubefact.com/integracion) · [Registro](https://www.nubefact.com/registro)

---

## 9. Estado de implementación (2026-08-02) — Fase 1 PRE-CONSTRUIDA

El adaptador ya está escrito y listo para conectar (falta solo ruta+token+series DEMO):

- **`Provider/Nubefact/NubefactConfig`**: lee `NUBEFACT_URL/TOKEN/AMBIENTE/TIMEOUT` solo de entorno (token nunca en código). `.env` versionado con valores vacíos; los reales van en `.env.local`.
- **`Provider/Nubefact/NubefactProvider`** (implementa la interfaz): arma el JSON de NubeFact desde el comprobante y la venta (precios con IGV incluido → calcula base e IGV hacia atrás y **suma los ítems** para que el total del encabezado reconcilie con el detalle), envía por **cURL nativo** (mismo patrón robusto que APISPERU, con CA bundle opcional) y traduce la respuesta a `ProviderResult` (aceptado/rechazado/pendiente, hash, cadena QR, y **enlaces PDF/XML/CDR**).
- **`ProviderResult`** y **`ElectronicDocument`** extendidos con `pdfUrl/xmlUrl/cdrUrl`; migración `Version20260802120000` agrega las 3 columnas. El frontend muestra botones **PDF/XML/CDR (SUNAT)** cuando el proveedor los devuelve.
- **Alias sin cambiar**: sigue `SimulatedProvider`. Para activar NubeFact: poner credenciales en `.env.local` y cambiar una línea en `services.yaml` al `NubefactProvider`.

**Mapa de tipos:** comprobante 01→1(Factura), 03→2(Boleta), 07→3(NC), 08→4(ND). Cliente: RUC→6, DNI→1, CE→4, Pasaporte→7, sin datos→'-'.

**Pendiente de validar en DEMO:** nombres exactos de campos y reconciliación de céntimos (el punto único a ajustar es `buildPayload()`). **Siguientes fases sobre esta base:** boletas simples (≤ S/700 «Público general»), Notas de Crédito/Débito (07/08) y Guía de Remisión (módulo nuevo).
