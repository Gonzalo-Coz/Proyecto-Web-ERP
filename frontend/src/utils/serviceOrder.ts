import type { ServiceOrderSummary } from '@/types/workshop'

/**
 * Genera e imprime la "Orden de Servicio" (Fase 1 — recepción de la moto),
 * con el diseño de la plantilla: datos del cliente/moto autocompletados, e
 * inventario, testigos, combustible, daños y firmas en blanco para llenar a
 * mano. Se abre en una ventana lista para imprimir o guardar como PDF.
 */
export function printServiceOrder(o: ServiceOrderSummary): void {
  const esc = (v: unknown): string =>
    v === null || v === undefined || v === ''
      ? ''
      : String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c] as string)

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
  .head { display:flex; justify-content:space-between; align-items:flex-start; }
  .head h1 { font-size:26px; margin:0; text-align:center; flex:1; }
  .head .sub { text-align:center; font-weight:bold; font-size:13px; margin-top:2px; }
  .folio { text-align:right; font-weight:bold; font-size:13px; }
  .folio .n { border-bottom:2px solid #111; min-width:130px; display:inline-block; font-size:16px; padding:2px 4px; text-align:center; }
  .band { background:#e5e7eb; text-align:center; font-weight:bold; padding:4px; margin:10px 0 6px; letter-spacing:.5px; }
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
      <div style="flex:1">
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

  const w = window.open('', '_blank')
  if (!w) return
  w.document.open()
  w.document.write(html)
  w.document.close()
}
