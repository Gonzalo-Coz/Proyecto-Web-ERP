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
  const co = doc.company
  const igvRate = doc.igvRate ?? 18
  const totalNum = Number(doc.total ?? 0)
  const aviso =
    doc.status !== 'ACEPTADO'
      ? `<div class="warn">COMPROBANTE ${esc(doc.status)} — aún no validado por SUNAT (sin validez tributaria)</div>`
      : ''

  return `
    <div class="doc ${format}">
      <div class="head">
        <div class="emp">
          ${logoTag(co?.logo)}
          <div class="emp-name">${esc(co?.name)}</div>
          ${co?.address ? `<div>${esc(co.address)}</div>` : ''}
          ${co?.phone || co?.email ? `<div>${esc(co?.phone)}${co?.phone && co?.email ? ' · ' : ''}${esc(co?.email)}</div>` : ''}
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
        ${doc.customerAddress ? `<div><b>Dirección:</b> ${esc(doc.customerAddress)}</div>` : ''}
        <div><b>Fecha de emisión:</b> ${esc(doc.issueDate)}</div>
      </div>

      <table>
        <thead>
          <tr><th class="c">Cant.</th><th>Descripción</th><th class="r">P. Unit</th><th class="r">Importe</th></tr>
        </thead>
        <tbody>${itemsRows(doc)}</tbody>
      </table>

      <div class="tot">
        ${Number(doc.discountTotal ?? 0) > 0 ? `<div><span>Descuentos:</span><span>${money(doc.discountTotal)}</span></div>` : ''}
        <div><span>Op. Gravada:</span><span>${money(doc.subtotal)}</span></div>
        <div><span>IGV (${igvRate}%):</span><span>${money(doc.igv)}</span></div>
        <div class="grand"><span>IMPORTE TOTAL:</span><span>${money(doc.total)}</span></div>
      </div>

      <div class="letras">SON: ${numeroALetras(totalNum)}</div>

      <div class="foot">
        ${doc.qrData ? `<div class="qr"><div class="qr-ph">QR</div><div class="qr-data">${esc(doc.qrData)}</div></div>` : ''}
        ${doc.hash ? `<div class="hash"><b>Hash:</b> ${esc(doc.hash)}</div>` : ''}
        <div class="rep">Representación impresa del comprobante electrónico.</div>
        ${aviso}
      </div>
    </div>`
}

function css(format: PrintFormat): string {
  const page = format === 'ticket' ? '@page { size: 80mm auto; margin: 3mm; }' : '@page { size: A4; margin: 12mm; }'
  const base = format === 'ticket' ? 11 : 12
  const width = format === 'ticket' ? 'width: 74mm;' : 'width: 100%;'
  return `
    ${page}
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
    .hash { margin-top: 6px; word-break: break-all; }
    .rep { margin-top: 6px; color: #555; font-style: italic; }
    .warn { margin-top: 6px; color: #b91c1c; font-weight: 700; }
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
  const w = window.open('', '_blank', 'width=460,height=700')
  if (!w) return
  w.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Cotización ${esc(doc.saleNumber)}</title><style>${css('a4')}</style></head><body>${body}</body></html>`)
  w.document.close()
  w.focus()
  setTimeout(() => w.print(), 350)
}

export function printComprobante(doc: InvoiceDocument, format: PrintFormat): void {
  const w = window.open('', '_blank', 'width=460,height=700')
  if (!w) return
  w.document.write(
    `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${esc(doc.fullNumber)}</title><style>${css(format)}</style></head><body>${bodyHtml(doc, format)}</body></html>`,
  )
  w.document.close()
  w.focus()
  setTimeout(() => {
    w.print()
  }, 350)
}
