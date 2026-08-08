import type { InvoiceDocument } from '@/types/invoicing'

/* ============================================================
   Número a letras (español) para el "SON:" del comprobante.
   ============================================================ */
const UNI = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE']
const DIECI = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE']
const DEC = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA']
const CEN = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS']

function seccion(n: number): string {
  if (n === 0) return ''
  if (n === 100) return 'CIEN'
  let out = ''
  const c = Math.floor(n / 100)
  const r = n % 100
  if (c > 0) out += CEN[c] + ' '
  if (r > 0) {
    if (r < 10) out += UNI[r]
    else if (r < 20) out += DIECI[r - 10]
    else if (r < 30) out += r === 20 ? 'VEINTE' : 'VEINTI' + UNI[r - 20]
    else {
      out += DEC[Math.floor(r / 10)]
      if (r % 10 > 0) out += ' Y ' + UNI[r % 10]
    }
  }
  return out.trim()
}

function enteroALetras(n: number): string {
  if (n === 0) return 'CERO'
  const millones = Math.floor(n / 1_000_000)
  const miles = Math.floor((n % 1_000_000) / 1000)
  const resto = n % 1000
  let out = ''
  if (millones > 0) out += (millones === 1 ? 'UN MILLON' : seccion(millones) + ' MILLONES') + ' '
  if (miles > 0) out += (miles === 1 ? 'MIL' : seccion(miles) + ' MIL') + ' '
  if (resto > 0) out += seccion(resto)
  return out.trim()
}

export function numeroALetras(total: number, moneda = 'SOLES'): string {
  const entero = Math.floor(total)
  const cent = Math.round((total - entero) * 100)
  return `${enteroALetras(entero)} CON ${String(cent).padStart(2, '0')}/100 ${moneda}`
}

/* ============================================================
   Construcción e impresión del comprobante (A4 o ticket 80mm).
   Se abre en una ventana aislada para no arrastrar estilos del ERP.
   ============================================================ */
export type PrintFormat = 'a4' | 'ticket'

const money = (v: string | number | null | undefined): string =>
  `S/ ${Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const esc = (s: string | null | undefined): string =>
  String(s ?? '').replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c] ?? c)

/** URL absoluta del origen para que el logo cargue en la ventana de impresión. */
const logoUrl = (path: string | null | undefined): string => {
  if (!path) return ''
  const p = path.startsWith('http') ? path : `${window.location.origin}${path.startsWith('/') ? '' : '/'}${path}`
  return p
}

/** Bloque del logo de la empresa para el encabezado del comprobante. */
const logoTag = (path: string | null | undefined): string => {
  const url = logoUrl(path)
  return url ? `<img class="logo" src="${esc(url)}" alt="logo" />` : ''
}

function itemsRows(doc: InvoiceDocument): string {
  return (doc.items ?? [])
    .map(
      (i) => `<tr>
        <td class="c">${i.quantity}</td>
        <td>${esc(i.description).replace(/\n/g, '<br>')}</td>
        <td class="r">${money(i.unitPrice)}</td>
        <td class="r">${money(i.lineTotal)}</td>
      </tr>`,
    )
    .join('')
}

function bodyHtml(doc: InvoiceDocument, format: PrintFormat): string {
  return format === 'a4' ? a4Body(doc) : ticketBody(doc)
}

/** Ticket 80mm: compacto. */
function ticketBody(doc: InvoiceDocument): string {
  const co = doc.company
  const igvRate = doc.igvRate ?? 18
  const aviso =
    doc.status !== 'ACEPTADO'
      ? `<div class="warn">COMPROBANTE ${esc(doc.status)} — aún no validado por SUNAT (sin validez tributaria)</div>`
      : ''
  return `
    <div class="doc ticket">
      <div class="head">
        <div class="emp">
          ${logoTag(co?.logo)}
          <div class="emp-name">${esc(co?.name)}</div>
          ${co?.address ? `<div>${esc(co.address)}</div>` : ''}
          ${co?.phone ? `<div>${esc(co?.phone)}</div>` : ''}
        </div>
        <div class="box">
          <div>RUC ${esc(co?.ruc)}</div>
          <div class="box-type">${esc(doc.docTypeName)}</div>
          <div class="box-num">${esc(doc.fullNumber)}</div>
        </div>
      </div>
      <div class="cli">
        <div><b>Cliente:</b> ${esc(doc.customerName)}</div>
        <div><b>Doc:</b> ${esc(doc.customerDocument)}</div>
        <div><b>Fecha:</b> ${esc(doc.issueDate)}</div>
      </div>
      <table>
        <thead><tr><th class="c">Cant.</th><th>Descripción</th><th class="r">P.Unit</th><th class="r">Importe</th></tr></thead>
        <tbody>${itemsRows(doc)}</tbody>
      </table>
      <div class="tot">
        <div><span>Op. Gravada:</span><span>${money(doc.subtotal)}</span></div>
        <div><span>IGV (${igvRate}%):</span><span>${money(doc.igv)}</span></div>
        <div class="grand"><span>TOTAL:</span><span>${money(doc.total)}</span></div>
      </div>
      <div class="letras">SON: ${numeroALetras(Number(doc.total ?? 0))}</div>
      <div class="foot">
        ${doc.qrData ? '<div id="qrbox" class="ticket-qr"></div>' : ''}
        ${doc.hash ? `<div class="hash"><b>Hash:</b> ${esc(doc.hash)}</div>` : ''}
        <div class="rep">Representación impresa de la ${esc(doc.docTypeName)}.</div>
        <div class="rep">Consulte su comprobante en el portal web de SUNAT.</div>
        ${aviso}
      </div>
    </div>`
}

/** A4: diseño formal con recuadros, totales y logo de la tienda. */
function a4Body(doc: InvoiceDocument): string {
  const co = doc.company
  const igvRate = doc.igvRate ?? 18
  const aviso =
    doc.status !== 'ACEPTADO'
      ? ''
      : ''
  const rows = (doc.items ?? [])
    .map(
      (i, n) => `<tr>
        <td class="c">${n + 1}</td>
        <td class="c">${i.quantity}</td>
        <td>${esc(i.description).replace(/\n/g, '<br>')}</td>
        <td class="r">${money(i.unitPrice)}</td>
        <td class="r">${money(i.lineTotal)}</td>
      </tr>`,
    )
    .join('')

  return `
    <div class="doc a4">
      <div class="a4-head">
        <div class="a4-emp">
          ${logoTag(co?.logo)}
          <div class="a4-empinfo">
            <div class="a4-name">${esc(co?.name)}</div>
            ${co?.tradeName ? `<div>${esc(co.tradeName)}</div>` : ''}
            ${co?.address ? `<div>${esc(co.address)}</div>` : ''}
            ${co?.phone ? `<div>Tel: ${esc(co.phone)}</div>` : ''}
            ${co?.email ? `<div>${esc(co.email)}</div>` : ''}
          </div>
        </div>
        <div class="a4-box">
          <div><b>RUC:</b> ${esc(co?.ruc)}</div>
          <div class="a4-boxtype">${esc(doc.docTypeName)}</div>
          <div class="a4-boxnum">${esc(doc.fullNumber)}</div>
        </div>
      </div>

      <div class="a4-grid2">
        <div class="a4-panel">
          <div class="a4-panel-h">Datos del Receptor</div>
          <div class="a4-panel-b">
            <div><b>Cliente:</b> ${esc(doc.customerName)}</div>
            <div><b>Documento:</b> ${esc(doc.customerDocument)}</div>
            ${doc.customerAddress ? `<div><b>Dirección:</b> ${esc(doc.customerAddress)}</div>` : ''}
          </div>
        </div>
        <div class="a4-panel">
          <div class="a4-panel-h">Datos Generales</div>
          <div class="a4-panel-b">
            <div><b>Fecha de emisión:</b> ${esc(doc.issueDate)}</div>
            <div><b>Moneda:</b> Soles</div>
            <div><b>Tipo de operación:</b> Venta interna</div>
            <div><b>Forma de pago:</b> Contado</div>
          </div>
        </div>
      </div>

      <table class="a4-items">
        <thead><tr><th class="c">Ítem</th><th class="c">Cant.</th><th>Descripción</th><th class="r">P. Unitario</th><th class="r">Importe</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>

      <div class="a4-obs"><b>Observaciones:</b></div>

      <div class="a4-bottom">
        <div class="a4-left">
          <div class="a4-letras">SON: ${numeroALetras(Number(doc.total ?? 0))}</div>
        </div>
        <div class="a4-tot">
          ${Number(doc.discountTotal ?? 0) > 0 ? `<div><span>Descuento total</span><span>${money(doc.discountTotal)}</span></div>` : ''}
          <div><span>Op. Gravada</span><span>${money(doc.subtotal)}</span></div>
          <div><span>IGV (${igvRate}%)</span><span>${money(doc.igv)}</span></div>
          <div class="a4-grand"><span>Importe Total</span><span>${money(doc.total)}</span></div>
        </div>
      </div>

      ${banksHtml(co)}

      <!-- Pie fijo al fondo de la hoja: QR + hash + representación -->
      <div class="a4-footer">
        <div class="a4-qrcol">
          ${doc.qrData ? '<div id="qrbox" class="a4-qr"></div>' : ''}
        </div>
        <div class="a4-footinfo">
          ${doc.hash ? `<div class="hash"><b>Hash:</b> ${esc(doc.hash)}</div>` : ''}
          <div class="rep">Representación impresa de la ${esc(doc.docTypeName)}.</div>
          <div class="rep">Consulte su comprobante en el portal web de SUNAT.</div>
          ${aviso}
        </div>
      </div>
    </div>`
}

/** Tabla de cuentas bancarias para el pie del comprobante. */
function banksHtml(co: InvoiceDocument['company']): string {
  const banks = co?.banks ?? []
  if (banks.length === 0) return ''
  const rows = banks
    .map((b) => `<tr><td>${esc(b.name)}</td><td>${esc(b.account)}</td><td>${esc(b.cci)}</td></tr>`)
    .join('')
  return `
    <table class="a4-bank">
      <thead><tr><th>Entidad Financiera</th><th>Código de Cuenta</th><th>Código Interbancario (CCI)</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>`
}

function css(format: PrintFormat): string {
  const page = format === 'ticket' ? '@page { size: 80mm auto; margin: 3mm; }' : '@page { size: A4; margin: 12mm; }'
  const base = format === 'ticket' ? 11 : 12
  const width = format === 'ticket' ? 'width: 74mm;' : 'width: 100%;'
  const a4Sheet =
    format === 'a4'
      ? `
    @media screen {
      body { background: #525659; padding: 24px 0; }
      .doc.a4 { background: #fff; width: 210mm; min-height: 296mm; padding: 14mm; box-shadow: 0 2px 14px rgba(0,0,0,.5); margin: 0 auto; display: flex; flex-direction: column; }
    }
    @media print {
      html, body { height: 100%; background: #fff; padding: 0; }
      .doc.a4 { box-shadow: none; width: auto; min-height: 262mm; padding: 0; display: flex; flex-direction: column; }
    }`
      : ''

  return `
    ${page}
    ${a4Sheet}
    * { box-sizing: border-box; }
    body { font-family: ${format === 'ticket' ? 'monospace' : 'Arial, Helvetica, sans-serif'}; font-size: ${base}px; color: #111; margin: 0; }
    .doc { ${width} margin: 0 auto; }
    .head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; ${format === 'ticket' ? 'flex-direction: column; text-align: center;' : ''} }
    .logo { max-height: ${format === 'ticket' ? 40 : 60}px; max-width: ${format === 'ticket' ? '70mm' : '240px'}; object-fit: contain; margin-bottom: 4px; ${format === 'ticket' ? 'margin-left: auto; margin-right: auto; display: block;' : ''} }
    .emp-name { font-weight: 700; font-size: ${base + 2}px; }
    .box { border: 1.5px solid #111; border-radius: 6px; padding: 6px 10px; text-align: center; ${format === 'ticket' ? 'width: 100%; margin-top: 6px;' : 'min-width: 170px;'} }
    .box-type { font-weight: 700; margin-top: 2px; }
    .box-num { font-weight: 700; font-size: ${base + 1}px; }
    .cli { margin: 10px 0; border-top: 1px dashed #999; border-bottom: 1px dashed #999; padding: 6px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { padding: 3px 4px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
    th { border-bottom: 1px solid #111; font-size: ${base - 1}px; text-transform: uppercase; }
    .c { text-align: center; } .r { text-align: right; white-space: nowrap; }
    .tot { margin-top: 8px; margin-left: auto; ${format === 'ticket' ? 'width: 100%;' : 'width: 45%;'} }
    .tot > div { display: flex; justify-content: space-between; padding: 2px 0; }
    .grand { border-top: 1.5px solid #111; margin-top: 4px; padding-top: 4px !important; font-weight: 700; font-size: ${base + 2}px; }
    .letras { margin-top: 8px; font-weight: 600; text-transform: uppercase; }
    .foot { margin-top: 12px; font-size: ${base - 1}px; }
    .qr { display: flex; align-items: center; gap: 8px; ${format === 'ticket' ? 'flex-direction: column; text-align: center;' : ''} }
    .qr-ph { width: 70px; height: 70px; border: 1.5px solid #111; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #888; }
    .qr-data { font-size: 8px; word-break: break-all; color: #444; }
    .ticket-qr { width: 96px; height: 96px; margin: 8px auto 0; }
    .ticket-qr img, .ticket-qr canvas { width: 96px !important; height: 96px !important; }
    .hash { margin-top: 6px; word-break: break-all; }
    .rep { margin-top: 6px; color: #555; font-style: italic; }
    .warn { margin-top: 6px; color: #6b7280; font-style: italic; }

    /* ===== Diseño A4 formal ===== */
    .a4-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; border-bottom: 3px solid #12233A; padding-bottom: 10px; }
    .a4-emp { display: flex; gap: 14px; align-items: center; }
    .a4-name { font-weight: 800; font-size: 16px; color: #12233A; }
    .a4-empinfo div { font-size: 11px; color: #333; line-height: 1.35; }
    .a4-box { border: 2px solid #12233A; border-radius: 10px; padding: 10px 18px; text-align: center; min-width: 210px; }
    .a4-boxtype { font-weight: 800; color: #12233A; margin-top: 3px; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
    .a4-boxnum { font-weight: 800; font-size: 15px; margin-top: 2px; }
    .a4-grid2 { display: flex; gap: 10px; margin-top: 12px; }
    .a4-panel { flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
    .a4-panel-h { background: #12233A; color: #fff; font-weight: 700; font-size: 10px; padding: 5px 10px; text-transform: uppercase; letter-spacing: .6px; }
    .a4-panel-b { padding: 7px 10px; font-size: 11px; }
    .a4-panel-b div { margin: 1.5px 0; }
    .a4-items { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .a4-items th { background: #eef2f7; border: 1px solid #cbd5e1; padding: 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
    .a4-items td { border: 1px solid #e2e8f0; padding: 6px; font-size: 11px; vertical-align: top; }
    .a4-obs { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; margin-top: 8px; font-size: 11px; min-height: 40px; }
    .a4-bottom { display: flex; justify-content: space-between; gap: 16px; margin-top: 12px; align-items: flex-start; }
    .a4-left { flex: 1; }
    .a4-letras { font-weight: 700; text-transform: uppercase; font-size: 11px; margin-bottom: 10px; }
    .a4-tot { width: 44%; }
    .a4-tot > div { display: flex; justify-content: space-between; padding: 4px 8px; font-size: 12px; }
    .a4-grand { border-top: 2px solid #12233A; margin-top: 4px; padding-top: 6px !important; font-weight: 800; font-size: 15px; color: #12233A; }
    .a4-qr { width: 120px; height: 120px; }
    .a4-qr img, .a4-qr canvas { width: 120px !important; height: 120px !important; }
    .a4-bank { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .a4-bank th { background: #12233A; color: #fff; font-size: 9px; padding: 6px 8px; text-transform: uppercase; letter-spacing: .4px; text-align: left; }
    .a4-bank td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 11px; }
    .a4-footer { display: flex; align-items: flex-end; gap: 16px; margin-top: auto; padding-top: 12px; border-top: 2px solid #12233A; }
    .a4-qrcol { flex-shrink: 0; }
    .a4-footinfo { flex: 1; font-size: 10px; color: #555; align-self: center; }
    .a4-footinfo .hash { word-break: break-all; margin-bottom: 4px; color: #333; }
    .a4-footinfo .rep { margin-top: 3px; font-style: italic; }
    .a4-warn { margin-top: 6px; color: #6b7280; font-style: italic; font-size: 10px; }
    .a4-note { margin-top: 6px; color: #6b7280; font-style: italic; font-size: 10px; }
  `
}

/** Documento mínimo para imprimir una cotización (copia al cliente). */
export interface CotizacionDoc {
  saleNumber: string
  saleDate: string
  customerName: string
  customerDocument: string
  customerAddress?: string | null
  subtotal: string
  igv: string
  total: string
  totalDiscount?: string
  igvRate?: number
  company?: { name: string; ruc: string; address: string; phone: string; email: string; logo?: string | null }
  items?: { description: string; quantity: number; unitPrice: string; lineTotal: string }[]
}

export function printCotizacion(doc: CotizacionDoc): void {
  const co = doc.company
  const igvRate = doc.igvRate ?? 18
  const rows = (doc.items ?? [])
    .map(
      (i) => `<tr><td class="c">${i.quantity}</td><td>${esc(i.description).replace(/\n/g, '<br>')}</td>
        <td class="r">${money(i.unitPrice)}</td><td class="r">${money(i.lineTotal)}</td></tr>`,
    )
    .join('')
  const body = `
    <div class="doc a4">
      <div class="head">
        <div class="emp">
          ${logoTag(co?.logo)}
          <div class="emp-name">${esc(co?.name)}</div>
          ${co?.address ? `<div>${esc(co.address)}</div>` : ''}
          ${co?.phone || co?.email ? `<div>${esc(co?.phone)}${co?.phone && co?.email ? ' · ' : ''}${esc(co?.email)}</div>` : ''}
        </div>
        <div class="box">
          <div>RUC ${esc(co?.ruc)}</div>
          <div class="box-type">COTIZACIÓN</div>
          <div class="box-num">${esc(doc.saleNumber)}</div>
        </div>
      </div>
      <div class="cli">
        <div><b>Cliente:</b> ${esc(doc.customerName)}</div>
        <div><b>Doc:</b> ${esc(doc.customerDocument)}</div>
        ${doc.customerAddress ? `<div><b>Dirección:</b> ${esc(doc.customerAddress)}</div>` : ''}
        <div><b>Fecha:</b> ${esc(doc.saleDate)}</div>
      </div>
      <table>
        <thead><tr><th class="c">Cant.</th><th>Descripción</th><th class="r">P. Unit</th><th class="r">Importe</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <div class="tot">
        ${Number(doc.totalDiscount ?? 0) > 0 ? `<div><span>Descuentos:</span><span>${money(doc.totalDiscount)}</span></div>` : ''}
        <div><span>Op. Gravada:</span><span>${money(doc.subtotal)}</span></div>
        <div><span>IGV (${igvRate}%):</span><span>${money(doc.igv)}</span></div>
        <div class="grand"><span>TOTAL:</span><span>${money(doc.total)}</span></div>
      </div>
      <div class="letras">SON: ${numeroALetras(Number(doc.total ?? 0))}</div>
      <div class="foot">
        <div class="rep">COTIZACIÓN — no es un comprobante de pago. Precios con IGV incluido. Válida por 7 días.</div>
      </div>
    </div>`
  const w = window.open('', '_blank', `width=${screen.availWidth},height=${screen.availHeight},left=0,top=0`)
  if (!w) return
  w.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Cotización ${esc(doc.saleNumber)}</title><style>${css('a4')}</style></head><body>${body}</body></html>`)
  w.document.close()
  w.focus()
  setTimeout(() => w.print(), 350)
}

export function printComprobante(doc: InvoiceDocument, format: PrintFormat): void {
  // A4 a pantalla completa (se ve el documento entero); ticket en ventana angosta.
  const features =
    format === 'ticket'
      ? 'width=430,height=760,left=200,top=40'
      : `width=${screen.availWidth},height=${screen.availHeight},left=0,top=0`
  const w = window.open('', '_blank', features)
  if (!w) return
  // QR real: se genera en la ventana con qrcodejs (CDN), tanto en A4 como en ticket.
  const hasQr = !!doc.qrData
  const qrSize = format === 'ticket' ? 96 : 120
  const qrScript = hasQr
    ? `<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>try{new QRCode(document.getElementById('qrbox'),{text:${JSON.stringify(doc.qrData)},width:${qrSize},height:${qrSize},correctLevel:QRCode.CorrectLevel.M});}catch(e){}</script>`
    : ''
  w.document.write(
    `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${esc(doc.fullNumber)}</title><style>${css(format)}</style></head><body>${bodyHtml(doc, format)}${qrScript}</body></html>`,
  )
  w.document.close()
  w.focus()
  setTimeout(() => {
    w.print()
  }, hasQr ? 900 : 350)
}
