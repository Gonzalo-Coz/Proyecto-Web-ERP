import type { ServiceOrderSummary } from '@/types/workshop'

const escHtml = (v: unknown): string =>
  v === null || v === undefined || v === ''
    ? ''
    : String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c] as string)

// Carga html2pdf (html2canvas + jsPDF) desde CDN una sola vez.
let html2pdfLoading: Promise<void> | null = null
function loadHtml2pdf(): Promise<void> {
  if ((window as any).html2pdf) return Promise.resolve()
  if (html2pdfLoading) return html2pdfLoading
  html2pdfLoading = new Promise<void>((resolve, reject) => {
    const s = document.createElement('script')
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('No se pudo cargar el generador de PDF.'))
    document.head.appendChild(s)
  })
  return html2pdfLoading
}

async function waitImages(doc: Document): Promise<void> {
  await Promise.all(
    Array.from(doc.images).map((img) =>
      img.complete
        ? Promise.resolve()
        : new Promise<void>((r) => {
            img.onload = () => r()
            img.onerror = () => r()
          }),
    ),
  )
}

/**
 * Convierte el HTML del documento (una hoja A4) en un PDF real y lo abre en el
 * visor del navegador (con su botón de descarga). Si el generador no está
 * disponible (p. ej. CSP bloquea el CDN), cae a la página imprimible.
 * @param win ventana ya abierta en el clic (evita bloqueo de pop-ups tras el await)
 */
async function presentDoc(html: string, filename: string, win?: Window | null): Promise<void> {
  const w = win ?? window.open('', '_blank')
  if (w) w.document.write('<!doctype html><meta charset="utf-8"><p style="font-family:Arial;padding:24px;color:#334">Generando PDF…</p>')
  let container: HTMLDivElement | null = null
  try {
    await loadHtml2pdf()
    // Se extrae el CSS y el cuerpo del documento. El contenido se monta en el
    // documento PRINCIPAL (fuera de pantalla) para que el generador lea bien la
    // geometría; el CSS se inyecta SOLO en la copia que captura html2canvas
    // (opción onclone), así el diseño se aplica sin ensuciar los estilos de la app.
    const css = (html.match(/<style>([\s\S]*?)<\/style>/) ?? [, ''])[1] as string
    let body = (html.match(/<body>([\s\S]*?)<\/body>/) ?? [, html])[1] as string
    body = body.replace(/<div class="toolbar">[\s\S]*?<\/div>/, '')

    container = document.createElement('div')
    container.style.cssText = 'position:fixed;left:-10000px;top:0;width:210mm;background:#ffffff;'
    container.innerHTML = body
    document.body.appendChild(container)
    await waitImages(document)
    await new Promise((r) => setTimeout(r, 60))

    const target = (container.querySelector('.sheet') as HTMLElement) ?? container
    const blob: Blob = await (window as any)
      .html2pdf()
      .set({
        margin: [8, 8, 8, 8],
        filename,
        image: { type: 'jpeg', quality: 0.98 },
        pagebreak: { mode: ['css', 'legacy'] },
        html2canvas: {
          scale: 2,
          useCORS: true,
          backgroundColor: '#ffffff',
          windowWidth: 900,
          scrollX: 0,
          scrollY: 0,
          onclone: (clonedDoc: Document) => {
            const st = clonedDoc.createElement('style')
            // El CSS del documento + overrides para el PDF: sin el margen "auto"
            // (que corría la hoja) y sin forzar alto de página completa (que empujaba
            // a una 2da hoja); el margen de página lo da la opción `margin` de arriba.
            st.textContent = css + ' .toolbar{display:none!important} .sheet{margin:0!important;min-height:auto!important;box-shadow:none!important}'
            clonedDoc.head.appendChild(st)
          },
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      })
      .from(target)
      .outputPdf('blob')
    if (container.parentNode) container.parentNode.removeChild(container)
    const url = URL.createObjectURL(blob)
    if (w) w.location.href = url
    else window.open(url, '_blank')
  } catch {
    if (container && container.parentNode) container.parentNode.removeChild(container)
    // Fallback: página imprimible con el botón "Imprimir / Guardar PDF".
    if (w) {
      w.document.open()
      w.document.write(html)
      w.document.close()
    } else {
      const fw = window.open('', '_blank')
      if (fw) {
        fw.document.write(html)
        fw.document.close()
      }
    }
  }
}

interface MotoHistoryPart { code: string; description: string; quantity: number }
interface MotoHistoryIngreso { orderNumber: string; date: string; km: number | null; tipo: string; work: string; parts: MotoHistoryPart[] }
interface MotoHistoryData {
  company: { tradeName: string; name: string; ruc: string; address: string; logo: string }
  customer: { name: string; document: string; phone: string | null; email: string | null; address: string | null } | null
  moto: { brand: string; model: string; color: string; vin: string; year: number | null; plate: string | null }
  summary: { totalIngresos: number; lastKm: number | null; nextMaintenanceKm: number | null }
  ingresos: MotoHistoryIngreso[]
}

/**
 * Historia clínica de la moto: historial de ingresos a taller SIN precios, con
 * el detalle de repuestos de los últimos 2 mantenimientos. Imprimible / PDF.
 */
export function printMotoHistory(d: MotoHistoryData, win?: Window | null): void {
  const esc = escHtml
  const logoSrc = d.company.logo?.startsWith('http') ? d.company.logo : `${window.location.origin}${d.company.logo || ''}`
  const km = (n: number | null): string => (n === null || n === undefined ? '—' : Number(n).toLocaleString('es-PE'))

  const ingresoRows = d.ingresos.map((i) =>
    `<tr><td>${esc(i.date)}</td><td>${esc(i.orderNumber)}</td><td class="r">${km(i.km)}</td><td>${esc(i.tipo)}</td><td>${esc(i.work)}</td></tr>`,
  ).join('') || '<tr><td colspan="5" style="text-align:center;color:#888">Sin ingresos registrados.</td></tr>'

  const lastTwo = d.ingresos.filter((i) => i.parts.length > 0).slice(-2).reverse()
  const partsBlocks = lastTwo.map((i) => `
    <div class="subh">${esc(i.orderNumber)} · ${esc(i.date)} · ${km(i.km)} km</div>
    <table class="pt"><thead><tr><th style="width:24%">Código</th><th style="width:61%">Repuesto</th><th class="r" style="width:15%">Cant.</th></tr></thead>
    <tbody>${i.parts.map((p) => `<tr><td>${esc(p.code)}</td><td>${esc(p.description)}</td><td class="r">${p.quantity}</td></tr>`).join('')}</tbody></table>`).join('')

  const c = d.customer
  const html = `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Historia clínica ${esc(d.moto.vin)}</title>
<style>
  *{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;font-size:12px}
  .toolbar{position:sticky;top:0;background:#0f172a;color:#fff;padding:8px 14px;display:flex;justify-content:flex-end}
  .toolbar button{background:#fff;color:#0f172a;border:0;border-radius:6px;padding:6px 14px;font-weight:600;cursor:pointer}
  .sheet{width:210mm;min-height:297mm;margin:10px auto;padding:16mm 14mm;background:#fff}
  .head{display:flex;align-items:center;gap:14px;border-bottom:0.5px solid #cbd5e1;padding-bottom:10px;margin-bottom:12px}
  .head img{height:56px;max-width:150px;object-fit:contain}
  .head .c{flex:1;text-align:center}
  .biz{font-size:15px;font-weight:bold}.biz2{font-size:11px;color:#555}
  h1{font-size:18px;margin:4px 0 0}
  .lbl{font-weight:600;color:#555;font-size:11.5px}
  .grid{display:grid;gap:2px 20px;font-size:12px;margin-bottom:10px}
  .g2{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:repeat(3,1fr)}
  .sec{font-weight:bold;font-size:12.5px;margin:12px 0 4px}
  table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:11px}
  th,td{border:0.5px solid #cbd5e1;padding:5px 6px;text-align:left;word-wrap:break-word}
  th{background:#f1f5f9}.r{text-align:right}
  .pt{margin-bottom:8px}.subh{font-size:11.5px;color:#555;margin:6px 0 2px}
  .next{border:1px solid #0f172a;border-radius:6px;padding:8px 10px;margin-top:12px;font-size:12.5px}
  .signs{display:flex;gap:60px;margin-top:36px}
  .sign{flex:1;border-top:1px solid #111;text-align:center;padding-top:4px;font-size:11.5px;color:#555}
  @media print{.toolbar{display:none}.sheet{margin:0}@page{size:A4;margin:0}}
</style></head><body>
  <div class="toolbar"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
  <div class="sheet">
    <div class="head">
      <img src="${esc(logoSrc)}" onerror="this.style.display='none'" alt="logo">
      <div class="c">
        <div class="biz">${esc(d.company.tradeName)}</div>
        <div class="biz2">${esc(d.company.name)} · RUC ${esc(d.company.ruc)}${d.company.address ? ' · ' + esc(d.company.address) : ''}</div>
        <h1>Historial de servicios del vehículo</h1>
      </div>
      <div style="width:56px"></div>
    </div>

    <div class="lbl">Datos del cliente</div>
    <div class="grid g2">
      <div><span class="lbl">Nombre:</span> ${esc(c?.name ?? '—')}</div>
      <div><span class="lbl">Documento:</span> ${esc(c?.document ?? '—')}</div>
      <div><span class="lbl">Teléfono:</span> ${esc(c?.phone ?? '—')}</div>
      <div><span class="lbl">Email:</span> ${esc(c?.email ?? '—')}</div>
      <div style="grid-column:1/-1"><span class="lbl">Dirección:</span> ${esc(c?.address ?? '—')}</div>
    </div>

    <div class="lbl">Datos de la motocicleta</div>
    <div class="grid g3">
      <div><span class="lbl">Modelo:</span> ${esc(d.moto.brand)} ${esc(d.moto.model)}</div>
      <div><span class="lbl">Color:</span> ${esc(d.moto.color)}</div>
      <div><span class="lbl">Año:</span> ${esc(d.moto.year ?? '—')}</div>
      <div><span class="lbl">N° serie:</span> ${esc(d.moto.vin)}</div>
      <div><span class="lbl">Último km:</span> ${km(d.summary.lastKm)}</div>
      <div><span class="lbl">Ingresos:</span> ${d.summary.totalIngresos}</div>
    </div>

    <div class="sec">Ingresos a taller</div>
    <table>
      <thead><tr><th style="width:14%">Fecha</th><th style="width:15%">N° OT</th><th class="r" style="width:12%">Km</th><th style="width:22%">Tipo</th><th style="width:37%">Trabajo realizado</th></tr></thead>
      <tbody>${ingresoRows}</tbody>
    </table>

    ${partsBlocks ? `<div class="sec">Repuestos utilizados — últimos 2 mantenimientos</div>${partsBlocks}` : ''}

    <div class="next"><b>Próximo mantenimiento sugerido:</b> ${d.summary.nextMaintenanceKm ? 'a los <b>' + km(d.summary.nextMaintenanceKm) + ' km</b>' : 'según el kilometraje o el uso'}, o según el uso del vehículo.</div>

    <div class="signs">
      <div class="sign">Firma del cliente</div>
      <div class="sign">Responsable de taller</div>
    </div>
  </div>
</body></html>`

  void presentDoc(html, `historia-clinica-${(d.moto.vin || 'moto').replace(/[^\w-]/g, '')}.pdf`, win)
}

/**
 * Genera e imprime la "Orden de Servicio" (Fase 1 — recepción de la moto),
 * con el diseño de la plantilla: datos del cliente/moto autocompletados, e
 * inventario, testigos, combustible, daños y firmas en blanco para llenar a
 * mano. Se abre en una ventana lista para imprimir o guardar como PDF.
 */
export function printServiceOrder(o: ServiceOrderSummary, logo?: string): void {
  const esc = (v: unknown): string =>
    v === null || v === undefined || v === ''
      ? ''
      : String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c] as string)
  const logoImg = logo ? `<img class="logo" src="${esc(logo)}" onerror="this.style.display='none'" alt="logo">` : ''

  // Datos de la moto: lo capturado en recepción (ya resuelto por el backend),
  // con el modelo de la unidad o la descripción libre para motos externas.
  const modelo = o.motoModel ?? (o.motorcycleUnitId ? '' : (o.motorcycleLabel ?? ''))
  const marca = o.motoBrand ?? ''
  const color = o.motoColor ?? ''
  const serie = o.motoSerial ?? ''

  const field = (label: string, value: string, width = 'auto'): string =>
    `<div class="fld" style="flex:1 1 ${width}"><span class="lbl">${label}</span><span class="val">${value || '&nbsp;'}</span></div>`

  const invItems = [
    'Espejos', 'Faro delantero', 'Direccionales', 'Tapón de gasolina', 'Pedales', 'Claxón',
    'Asientos', 'Luz de stop trasero', 'Cubiertas completas', 'Tacómetros', 'Estéreo', 'Parabrisas',
    'Tapón de radiadores', 'Filtro de aire', 'Batería', 'Llaves',
  ]
  const inv = invItems.map((i) => `<label class="chk"><span class="box"></span>${i}</label>`).join('')

  const html = `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
<title>Orden de Servicio ${esc(o.orderNumber)}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color:#111; margin:0; font-size:12px; }
  .toolbar { position:sticky; top:0; background:#0f172a; color:#fff; padding:8px 14px; display:flex; gap:10px; justify-content:flex-end; }
  .toolbar button { background:#fff; color:#0f172a; border:0; border-radius:6px; padding:6px 14px; font-weight:600; cursor:pointer; }
  .sheet { width:210mm; min-height:297mm; margin:10px auto; padding:14mm 12mm; background:#fff; }
  .head { display:flex; justify-content:space-between; align-items:center; gap:14px; border-bottom:3px solid #E30613; padding-bottom:10px; }
  .head .logo { height:62px; max-width:160px; object-fit:contain; }
  .head-c { flex:1; text-align:center; }
  .biz { font-size:17px; font-weight:bold; letter-spacing:.3px; }
  .biz2 { font-size:10.5px; color:#555; }
  .head h1 { font-size:20px; margin:4px 0 0; text-align:center; }
  .head .sub { text-align:center; font-weight:600; font-size:11.5px; color:#444; }
  .folio { text-align:right; font-weight:bold; font-size:12px; white-space:nowrap; }
  .folio .n { border:1.5px solid #111; border-radius:4px; min-width:120px; display:inline-block; font-size:15px; padding:3px 6px; text-align:center; margin-top:3px; }
  .band { background:#111827; color:#fff; text-align:center; font-weight:bold; padding:5px; margin:12px 0 6px; letter-spacing:.5px; border-radius:3px; font-size:11.5px; }
  .row { display:flex; gap:14px; flex-wrap:wrap; }
  .col { flex:1; }
  .fld { display:flex; gap:4px; align-items:flex-end; margin:5px 0; }
  .fld .lbl { font-weight:600; white-space:nowrap; }
  .fld .val { border-bottom:1px solid #111; flex:1; min-height:15px; padding:0 3px; }
  .box2 { border:1px solid #cbd5e1; min-height:46px; border-radius:3px; }
  .inv { display:grid; grid-template-columns:repeat(3,1fr); gap:4px 10px; }
  .chk { display:flex; align-items:center; gap:6px; }
  .chk .box { width:12px; height:12px; border:1.5px solid #111; display:inline-block; }
  .fuelscale { display:flex; justify-content:space-between; align-items:center; border:1px solid #111; border-radius:8px; padding:8px 14px; margin-top:4px; }
  .fuelscale span { font-weight:bold; font-size:14px; }
  .dmgs { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:6px; }
  .dmg-l { text-align:center; font-weight:600; margin-bottom:3px; }
  .dmg-b { border:1px solid #94a3b8; height:120px; border-radius:3px; }
  .signs { display:flex; gap:60px; margin-top:26px; }
  .sign { flex:1; border-top:1px solid #111; text-align:center; padding-top:4px; }
  @media print { .toolbar { display:none; } .sheet { margin:0; } @page { size:A4; margin:0; } }
</style></head>
<body>
  <div class="toolbar"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
  <div class="sheet">
    <div class="head">
      ${logoImg}
      <div class="head-c">
        <div class="biz">YAMAHA GLOBAL MOTORS</div>
        <div class="biz2">Integra Global Motors S.A.C. · RUC 20615585271</div>
        <h1>Orden de Servicio</h1>
        <div class="sub">Mantenimiento y Reparación de Motocicletas</div>
      </div>
      <div class="folio">N° DE FOLIO<br><span class="n">${esc(o.orderNumber)}</span></div>
    </div>

    <div class="band">DATOS DEL CLIENTE Y LA MOTOCICLETA</div>
    <div class="row">
      <div class="col">
        ${field('Marca:', esc(marca))}
        <div class="row">${field('Modelo:', esc(modelo))}${field('Color:', esc(color))}</div>
        <div class="row">${field('Kilometraje:', esc(o.mileage ?? ''))}${field('Placas:', esc(o.plate ?? ''))}</div>
        ${field('N° de serie:', esc(serie))}
      </div>
      <div class="col">
        ${field('Ingreso:', esc(o.entryDate) + (o.entryTime ? ' &nbsp; ' + esc(o.entryTime) : ''))}
        ${field('Tiempo estimado:', o.estimatedHours ? esc(Number(o.estimatedHours)) + ' horas' : '')}
        ${field('Nombre:', esc(o.customerName) + (o.customerDocument ? ' (' + esc(o.customerDocument) + ')' : ''))}
        ${o.broughtBy ? field('A nombre de / ingresa:', esc(o.broughtBy)) : ''}
        ${field('Teléfono:', esc(o.contactPhone ?? ''))}
        ${field('Email:', esc(o.contactEmail ?? ''))}
      </div>
    </div>

    <div class="band">TRABAJO A REALIZAR</div>
    <div class="box2" style="padding:4px">${esc(o.diagnosis ?? '')}</div>

    <div class="band">OBSERVACIONES</div>
    <div class="box2" style="padding:4px">${esc(o.notes ?? '')}</div>

    <div class="band">INVENTARIO</div>
    <div class="row">
      <div class="inv" style="flex:2">${inv}</div>
      <div class="col">
        <div style="font-weight:600;text-align:center">Nivel de combustible</div>
        <div style="text-align:center;font-size:10px;color:#64748b;margin-bottom:2px">(encerrar el nivel en que llegó)</div>
        <div class="fuelscale"><span>E</span><span>¼</span><span>½</span><span>¾</span><span>F</span></div>
      </div>
    </div>

    <div class="signs">
      <div class="sign">Firma del cliente</div>
      <div class="sign">Firma del responsable / técnico</div>
    </div>
  </div>
</body></html>`

  void presentDoc(html, `orden-servicio-${esc(o.orderNumber).replace(/[^\w-]/g, '')}.pdf`)
}

/**
 * Acta de Entrega del vehículo (Fase 3): documento profesional con lo realizado,
 * el total y el próximo mantenimiento sugerido. Para imprimir o guardar en PDF.
 */
export function printServiceDelivery(o: ServiceOrderSummary, logo?: string): void {
  const esc = (v: unknown): string =>
    v === null || v === undefined || v === ''
      ? ''
      : String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c] as string)
  const logoImg = logo ? `<img class="logo" src="${esc(logo)}" onerror="this.style.display='none'" alt="logo">` : ''

  const items = o.items ?? []
  const plan = items.filter((i) => i.fromPlan)
  const extra = items.filter((i) => !i.fromPlan)
  const money = (n: number): string => `S/ ${n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  const rowsOf = (list: typeof items): string =>
    list.map((i) => `<tr><td>${i.itemType === 'PART' ? 'Repuesto' : 'Mano de obra'}</td><td>${esc(i.description)}</td><td class="r">${i.quantity}</td><td class="r">${esc(i.unitPrice)}</td><td class="r">${esc(i.lineTotal)}</td></tr>`).join('')

  const moto = [o.motoBrand, o.motoModel, o.motoColor].filter(Boolean).join(' ') || (o.motorcycleLabel ?? '')
  const nextKm = o.nextMaintenanceKm ? `${o.nextMaintenanceKm.toLocaleString('es-PE')} km` : 'según el kilometraje o el uso del vehículo'

  const html = `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
<title>Acta de Entrega ${esc(o.orderNumber)}</title>
<style>
  *{box-sizing:border-box;} body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;font-size:12px;}
  .toolbar{position:sticky;top:0;background:#0f172a;color:#fff;padding:8px 14px;display:flex;justify-content:flex-end;}
  .toolbar button{background:#fff;color:#0f172a;border:0;border-radius:6px;padding:6px 14px;font-weight:600;cursor:pointer;}
  .sheet{width:210mm;min-height:297mm;margin:10px auto;padding:16mm 14mm;background:#fff;}
  .dhead{display:flex;align-items:center;gap:14px;border-bottom:3px solid #E30613;padding-bottom:10px;margin-bottom:10px;}
  .dhead .logo{height:60px;max-width:150px;object-fit:contain;}
  .dhead .c{flex:1;text-align:center;}
  h1{font-size:20px;text-align:center;margin:6px 0 0;}
  .sub{text-align:center;font-size:11.5px;color:#555;margin-bottom:2px;}
  .biz{text-align:center;font-weight:bold;font-size:16px;}
  .biz2{text-align:center;font-size:10.5px;color:#555;}
  .band{background:#111827;color:#fff;font-weight:bold;padding:5px 8px;margin:12px 0 6px;border-radius:3px;letter-spacing:.4px;}
  .grid{display:flex;flex-wrap:wrap;gap:2px 24px;}
  .grid p{margin:2px 0;flex:1 1 45%;}
  table{width:100%;border-collapse:collapse;margin-top:4px;font-size:11px;}
  th,td{border:1px solid #cbd5e1;padding:4px 6px;text-align:left;}
  th{background:#f1f5f9;}
  td.r,th.r{text-align:right;}
  .tot{text-align:right;font-size:14px;margin-top:6px;}
  .next{border:1px solid #0f172a;border-radius:6px;padding:8px 10px;margin-top:12px;font-size:13px;}
  .signs{display:flex;gap:60px;margin-top:40px;}
  .sign{flex:1;border-top:1px solid #111;text-align:center;padding-top:4px;}
  @media print{.toolbar{display:none;}.sheet{margin:0;}@page{size:A4;margin:0;}}
</style></head>
<body>
  <div class="toolbar"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
  <div class="sheet">
    <div class="dhead">
      ${logoImg}
      <div class="c">
        <div class="biz">YAMAHA GLOBAL MOTORS</div>
        <div class="biz2">Integra Global Motors S.A.C. · RUC 20615585271</div>
        <h1>ACTA DE ENTREGA DEL VEHÍCULO</h1>
        <div class="sub">Orden de Servicio N° ${esc(o.orderNumber)}</div>
      </div>
    </div>

    <div class="band">DATOS</div>
    <div class="grid">
      <p><b>Cliente:</b> ${esc(o.customerName)}${o.customerDocument ? ' (' + esc(o.customerDocument) + ')' : ''}</p>
      <p><b>Motocicleta:</b> ${esc(moto)}</p>
      <p><b>Placa:</b> ${esc(o.plate ?? '—')}</p>
      <p><b>N° de serie:</b> ${esc(o.motoSerial ?? '—')}</p>
      <p><b>Kilometraje:</b> ${esc(o.mileage ?? '—')}</p>
      <p><b>Ingreso:</b> ${esc(o.entryDate)}${o.entryTime ? ' ' + esc(o.entryTime) : ''}</p>
      <p><b>Mecánico:</b> ${esc(o.mechanicName ?? '—')}</p>
      ${o.planModel ? `<p><b>Plan aplicado:</b> ${esc(o.planModel)} — ${esc(o.planKm ?? '')} km</p>` : ''}
    </div>

    ${plan.length ? `<div class="band">MANTENIMIENTO PROGRAMADO</div>
    <table><thead><tr><th>Tipo</th><th>Descripción</th><th class="r">Cant.</th><th class="r">P.Unit</th><th class="r">Total</th></tr></thead><tbody>${rowsOf(plan)}</tbody></table>` : ''}

    ${extra.length ? `<div class="band">TRABAJOS Y REPUESTOS ADICIONALES</div>
    <table><thead><tr><th>Tipo</th><th>Descripción</th><th class="r">Cant.</th><th class="r">P.Unit</th><th class="r">Total</th></tr></thead><tbody>${rowsOf(extra)}</tbody></table>` : ''}

    <p class="tot"><b>Total del servicio: ${money(Number(o.total))}</b></p>

    <div class="next"><b>Próximo mantenimiento sugerido:</b> a los <b>${nextKm}</b> o según el uso del vehículo. Se recomienda respetar el plan de mantenimiento para conservar la garantía.</div>

    <p style="margin-top:14px;font-size:11px;color:#444">El cliente declara recibir su motocicleta conforme, habiendo verificado los trabajos realizados.</p>

    <div class="signs">
      <div class="sign">Firma del cliente (conforme)</div>
      <div class="sign">Firma del responsable / técnico</div>
    </div>
  </div>
</body></html>`

  void presentDoc(html, `acta-entrega-${esc(o.orderNumber).replace(/[^\w-]/g, '')}.pdf`)
}
