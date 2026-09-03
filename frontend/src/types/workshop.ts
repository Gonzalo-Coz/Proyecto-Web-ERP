export const ORDER_STATUSES = [
  'RECIBIDA', 'EN_DIAGNOSTICO', 'ESPERANDO_REPUESTOS',
  'EN_REPARACION', 'LISTA_PARA_ENTREGA', 'ENTREGADA', 'GARANTIA',
] as const
// 'ANULADA' es un estado válido pero NO se elige por el selector (se usa el botón de anular).
export type OrderStatus = (typeof ORDER_STATUSES)[number] | 'ANULADA'

export interface ServiceOrderItem {
  id: number
  itemType: 'PART' | 'LABOR'
  sparePartId: number | null
  description: string
  quantity: number
  unitPrice: string
  lineTotal: string
  /** true = proviene del plan de mantenimiento (principal); false = adicional. */
  fromPlan?: boolean
}

export interface ServiceOrderSummary {
  id: number
  orderNumber: string
  customerId: number
  customerName: string
  customerDocument?: string
  contactPhone?: string | null
  contactEmail?: string | null
  broughtBy?: string | null
  planModel?: string | null
  planKm?: number | null
  nextMaintenanceKm?: number | null
  motoBrand?: string | null
  motoModel?: string | null
  motoColor?: string | null
  motoSerial?: string | null
  motorcycleUnitId: number | null
  motorcycleLabel: string | null
  plate: string | null
  mileage: number | null
  entryDate: string
  entryTime?: string | null
  estimatedDate: string | null
  estimatedHours?: string | null
  deliveredAt: string | null
  mechanicName: string | null
  diagnosis: string | null
  notes: string | null
  status: OrderStatus
  invoiceSaleId: number | null
  total: string
  items?: ServiceOrderItem[]
  /** Avisos al cargar un plan de mantenimiento (repuestos sin stock/no cargados). */
  planWarnings?: string[]
}

export interface ServiceOrderPayload {
  customerId: number
  broughtBy: string | null
  motoBrand: string | null
  motoColor: string | null
  motoSerial: string | null
  contactPhone: string | null
  contactEmail: string | null
  motorcycleUnitId: number | null
  motorcycleDescription: string | null
  plate: string | null
  mileage: number | null
  entryDate: string
  entryTime: string | null
  estimatedDate: string | null
  estimatedHours: number | null
  mechanicName: string | null
  diagnosis: string | null
  notes: string | null
}
