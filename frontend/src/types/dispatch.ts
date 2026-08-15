export interface DispatchItem {
  codigo: string
  descripcion: string
  cantidad: number
  unidad: string
}

export interface DispatchGuideItem {
  id: number
  fullNumber: string
  series: string
  correlative: number
  issueDate: string
  transferDate: string
  motive: string
  motiveName: string
  recipientDocType: string
  recipientDocNumber: string
  recipientName: string
  originAddress: string
  originUbigeo: string | null
  destinationAddress: string
  destinationUbigeo: string | null
  transportMode: string
  transportModeName: string
  carrierRuc: string | null
  carrierName: string | null
  vehiclePlate: string | null
  driverLicense: string | null
  driverName: string | null
  totalWeight: string
  weightUnit: string
  packages: number
  items: DispatchItem[]
  saleId: number | null
  saleNumber: string | null
  observations: string | null
  status: 'PENDIENTE' | 'ACEPTADO' | 'RECHAZADO' | 'ANULADO'
  hash: string | null
  qrData: string | null
  pdfUrl: string | null
  xmlUrl: string | null
  errorMessage: string | null
}

export interface DispatchGuidePayload {
  transferDate: string
  motive: string
  recipientDocType: string
  recipientDocNumber: string
  recipientName: string
  originAddress: string
  originUbigeo?: string | null
  destinationAddress: string
  destinationUbigeo?: string | null
  transportMode: string
  carrierRuc?: string | null
  carrierName?: string | null
  vehiclePlate?: string | null
  driverLicense?: string | null
  driverName?: string | null
  totalWeight: number
  packages: number
  saleId?: number | null
  observations?: string | null
  items: DispatchItem[]
}

export const DISPATCH_MOTIVES: Record<string, string> = {
  '01': 'Venta',
  '02': 'Compra',
  '04': 'Traslado entre establecimientos de la misma empresa',
  '08': 'Importación',
  '13': 'Otros',
}
