import type { DispatchGuideItem } from '@/types/dispatch'

/* ============================================================
   Representación impresa de la Guía de Remisión Remitente (GRE,
   tipo 09). Comparte el lenguaje visual de los comprobantes
   (encabezado navy #12233A, paneles, hoja A4) y se genera como
   PDF con html2pdf, igual que las boletas/facturas.
   ============================================================ */

export interface GuideCompany {
  name: string
  tradeName?: string | null
  ruc: string
  address?: string | null
  department?: string | null
  province?: string | null
  district?: string | null
  phone?: string | null
  email?: string | null
  logo?: string | null
}

const esc = (s: string | null | undefined): string =>
  String(s ?? '').replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c] ?? c)

const logoUrl = (path: string | null | undefined): string => {
  if (!path) return ''
  return path.startsWith('http') ? path : `${window.location.origin}${path.startsWith('/') ? '' : '/'}${path}`
}

const logoTag = (path: string | null | undefined): string => {
  const url = logoUrl(path)
  return url ? `<img class="logo" src="${esc(url)}" onerror="this.style.display='none'" alt="logo" />` : ''
}

function ubigeoStr(co: GuideCompany): string {
  const parts = [co.department, co.province, co.district].map((p) => (p ?? '').trim()).filter(Boolean)
  return parts.length ? ` - ${esc(parts.join(' - '))}` : ''
}

const DOC_TITLE = 'GUÍA DE REMISIÓN REMITENTE ELECTRÓNICA'

function itemRows(g: DispatchGuideItem): string {
  return (g.items ?? [])
    .map(
      (i, n) => `<tr>
        <td class="c">${n + 1}</td>
        <td class="c">${esc(i.codigo ?? '')}</td>
        <td class="c">${i.cantidad}</td>
        <td class="c">${esc(i.unidad ?? 'NIU')}</td>
        <td>${esc(i.descripcion).replace(/\n/g, '<br>')}</td>
      </tr>`,
    )
    .join('')
}

/** Bloque de transporte según modalidad (público: transportista / privado: vehículo). */
function transportBlock(g: DispatchGuideItem): string {
  if (g.transportMode === '01') {
    return `
      <div><b>Modalidad:</b> ${esc(g.transportModeName)}</div>
      ${g.carrierRuc ? `<div><b>RUC transportista:</b> ${esc(g.carrierRuc)}</div>` : ''}
      ${g.carrierName ? `<div><b>Transportista:</b> ${esc(g.carrierName)}</div>` : ''}`
  }
  return `
    <div><b>Modalidad:</b> ${esc(g.transportModeName)}</div>
    ${g.vehiclePlate ? `<div><b>Placa:</b> ${esc(g.vehiclePlate)}</div>` : ''}
    ${g.driverName ? `<div><b>Conductor:</b> ${esc(g.driverName)}</div>` : ''}
    ${g.driverLicense ? `<div><b>Licencia:</b> ${esc(g.driverLicense)}</div>` : ''}`
}

function bodyHtml(g: DispatchGuideItem, co: GuideCompany): string {
  const statusNote =
    g.status === 'ACEPTADO'
      ? `Representación impresa de la ${DOC_TITLE}.`
      : g.status === 'ANULADO'
        ? 'GUÍA ANULADA — sin validez para el traslado.'
        : `GUÍA ${esc(g.status)} — aún no aceptada por SUNAT (sin validez para el traslado).`

  return `
    <div class="doc a4">
      <div class="a4-head">
        <div class="a4-emp">
          ${logoTag(co.logo)}
          <div class="a4-empinfo">
            <div class="a4-name">${esc(co.tradeName || co.name)}</div>
            ${co.tradeName ? `<div><b>Razón social:</b> ${esc(co.name)}</div>` : ''}
            ${co.address ? `<div><b>Dirección:</b> ${esc(co.address)}${ubigeoStr(co)}</div>` : ''}
            ${co.phone ? `<div><b>Teléfono:</b> ${esc(co.phone)}</div>` : ''}
            ${co.email ? `<div>${esc(co.email)}</div>` : ''}
          </div>
        </div>
        <div class="a4-box">
          <div><b>RUC:</b> ${esc(co.ruc)}</div>
          <div class="a4-boxtype">${DOC_TITLE}</div>
          <div class="a4-boxnum">${esc(g.fullNumber)}</div>
        </div>
      </div>

      <div class="a4-grid2">
        <div class="a4-panel">
          <div class="a4-panel-h">Destinatario</div>
          <div class="a4-panel-b">
            <div><b>Nombre / Razón social:</b> ${esc(g.recipientName)}</div>
            <div><b>Documento:</b> ${esc(g.recipientDocType)} ${esc(g.recipientDocNumber)}</div>
          </div>
        </div>
        <div class="a4-panel">
          <div class="a4-panel-h">Datos del traslado</div>
          <div class="a4-panel-b">
            <div><b>Motivo:</b> ${esc(g.motiveName)}</div>
            <div><b>Fecha de emisión:</b> ${esc(g.issueDate)}</div>
            <div><b>Fecha de traslado:</b> ${esc(g.transferDate)}</div>
          </div>
        </div>
      </div>

      <div class="a4-grid2">
        <div class="a4-panel">
          <div class="a4-panel-h">Punto de partida</div>
          <div class="a4-panel-b">
            <div>${esc(g.originAddress)}</div>
            ${g.originUbigeo ? `<div><b>Ubigeo:</b> ${esc(g.originUbigeo)}</div>` : ''}
          </div>
        </div>
        <div class="a4-panel">
          <div class="a4-panel-h">Punto de llegada</div>
          <div class="a4-panel-b">
            <div>${esc(g.destinationAddress)}</div>
            ${g.destinationUbigeo ? `<div><b>Ubigeo:</b> ${esc(g.destinationUbigeo)}</div>` : ''}
          </div>
        </div>
      </div>

      <div class="a4-grid2">
        <div class="a4-panel">
          <div class="a4-panel-h">Transporte</div>
          <div class="a4-panel-b">${transportBlock(g)}</div>
        </div>
        <div class="a4-panel">
          <div class="a4-panel-h">Carga</div>
          <div class="a4-panel-b">
            <div><b>Peso bruto total:</b> ${esc(g.totalWeight)} ${esc(g.weightUnit)}</div>
            <div><b>N° de bultos:</b> ${g.packages}</div>
            ${g.relatedDocNumber ? `<div><b>Comprobante relacionado:</b> ${esc(g.relatedDocTypeName ?? '')} ${esc(g.relatedDocNumber)}</div>` : g.saleNumber ? `<div><b>Venta relacionada:</b> ${esc(g.saleNumber)}</div>` : ''}
          </div>
        </div>
      </div>

      <table class="a4-items">
        <thead><tr><th class="c">Ítem</th><th class="c">Código</th><th class="c">Cant.</th><th class="c">Unid.</th><th>Descripción</th></tr></thead>
        <tbody>${itemRows(g)}</tbody>
      </table>

      ${g.observations ? `<div class="a4-obs"><b>Observaciones:</b> ${esc(g.observations).replace(/\n/g, '<br>')}</div>` : ''}

      <div class="a4-footer">
        <div class="a4-qrcol">
          ${g.qrData ? '<div id="qrbox" class="a4-qr"></div>' : ''}
        </div>
        <div class="a4-footinfo">
          ${g.hash ? `<div class="hash"><b>Hash:</b> ${esc(g.hash)}</div>` : ''}
          <div class="rep">${statusNote}</div>
        </div>
      </div>
    </div>`
}

function css(): string {
  return `
    @page { size: A4; margin: 0; }
    @media screen {
      body { background: #525659; padding: 24px 0; margin: 0; }
      .doc.a4 { background: #fff; width: 210mm; min-height: auto; padding: 14mm; box-shadow: 0 2px 14px rgba(0,0,0,.5); margin: 0 auto; display: flex; flex-direction: column; }
    }
    @media print {
      html, body { background: #fff; padding: 0; margin: 0; }
      .doc.a4 { box-shadow: none; width: auto; min-height: auto; padding: 12mm; display: flex; flex-direction: column; }
    }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; }

    .a4-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 3px solid #12233A; padding-bottom: 10px; }
    .a4-emp { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
    .logo { max-height: 60px; max-width: 240px; object-fit: contain; }
    .a4-name { font-weight: 800; font-size: 17px; color: #12233A; line-height: 1.2; }
    .a4-empinfo div { font-size: 11px; color: #333; line-height: 1.45; }
    .a4-box { border: 2px solid #12233A; border-radius: 10px; padding: 10px 16px; text-align: center; width: 230px; flex-shrink: 0; }
    .a4-box > div:first-child { font-size: 11px; }
    .a4-boxtype { font-weight: 800; color: #12233A; margin-top: 5px; font-size: 11px; line-height: 1.25; text-transform: uppercase; letter-spacing: .3px; }
    .a4-boxnum { font-weight: 800; font-size: 16px; margin-top: 5px; letter-spacing: .5px; }

    .a4-grid2 { display: flex; gap: 10px; margin-top: 12px; }
    .a4-panel { flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
    .a4-panel-h { background: #12233A; color: #fff; font-weight: 700; font-size: 10px; padding: 5px 10px; text-transform: uppercase; letter-spacing: .6px; }
    .a4-panel-b { padding: 7px 10px; font-size: 11px; }
    .a4-panel-b div { margin: 1.5px 0; }

    .a4-items { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .a4-items th { background: #eef2f7; border: 1px solid #cbd5e1; padding: 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
    .a4-items td { border: 1px solid #e2e8f0; padding: 6px; font-size: 11px; vertical-align: top; }
    .c { text-align: center; }

    .a4-obs { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; margin-top: 10px; font-size: 11px; min-height: 40px; }

    .a4-footer { display: flex; align-items: flex-end; gap: 16px; margin-top: auto; padding-top: 12px; border-top: 2px solid #12233A; }
    .a4-qrcol { flex-shrink: 0; }
    .a4-qr { width: 120px; height: 120px; }
    .a4-qr img, .a4-qr canvas { width: 120px !important; height: 120px !important; }
    .a4-footinfo { flex: 1; font-size: 10px; color: #555; align-self: center; }
    .a4-footinfo .hash { word-break: break-all; margin-bottom: 4px; color: #333; }
    .a4-footinfo .rep { margin-top: 3px; font-style: italic; }
  `
}

function loadScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve()
    const s = document.createElement('script')
    s.src = src
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('No se pudo cargar ' + src))
    document.head.appendChild(s)
  })
}

/**
 * Genera la guía como PDF y la abre en el visor del navegador (misma mecánica
 * que los comprobantes): renderiza el HTML en un contenedor oculto, dibuja el QR
 * si existe y lo convierte con html2pdf.
 */
export async function openDispatchGuidePdf(g: DispatchGuideItem, co: GuideCompany): Promise<void> {
  const win = window.open('', '_blank')
  if (win) {
    win.document.write(
      `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${esc(g.fullNumber)}</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:28px;color:#334155">Generando el PDF…</body></html>`,
    )
    win.document.close()
  }

  const wrap = document.createElement('div')
  wrap.style.cssText = 'position:fixed;left:-99999px;top:0;'
  const styleEl = document.createElement('style')
  styleEl.textContent = css()
  const content = document.createElement('div')
  content.innerHTML = bodyHtml(g, co)
  wrap.appendChild(styleEl)
  wrap.appendChild(content)
  document.body.appendChild(wrap)

  try {
    await loadScript('https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.2/dist/html2pdf.bundle.min.js')

    if (g.qrData) {
      await loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js')
      const box = content.querySelector('#qrbox')
      const QR = (window as any).QRCode
      if (box && QR) {
        try {
          new QR(box, { text: g.qrData, width: 120, height: 120, correctLevel: QR.CorrectLevel.M })
        } catch {
          /* si falla el QR, igual se genera el PDF */
        }
      }
    }

    await new Promise((r) => setTimeout(r, 600))

    const el = content.firstElementChild as HTMLElement
    const opt = {
      margin: [6, 6, 6, 6],
      filename: `${g.fullNumber}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      pagebreak: { mode: ['css', 'legacy'] },
      html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    }

    const url: string = await (window as any).html2pdf().set(opt).from(el).output('bloburl')
    if (win) win.location.href = url
    else window.open(url, '_blank')
  } finally {
    document.body.removeChild(wrap)
  }
}
