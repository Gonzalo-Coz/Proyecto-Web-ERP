export const ORDER_STATUSES = [
  'RECIBIDA', 'EN_DIAGNOSTICO', 'ESPERANDO_REPUESTOS',
  'EN_REPARACION', 'LISTA_PARA_ENTREGA', 'ENTREGADA', 'GARANTIA',
] as const
export type OrderStatus = (typeof ORDER_STATUSES)[number]

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
  broughtBy?: string | null
  planModel?: string | null
  planKm?: number | null
  motorcycleUnitId: number | null
  motorcycleLabel: string | null
  plate: string | null
  mileage: number | null
  entryDate: string
  estimatedDate: string | null
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
  motorcycleUnitId: number | null
  motorcycleDescription: string | null
  plate: string | null
  mileage: number | null
  entryDate: string
  estimatedDate: string | null
  mechanicName: string | null
  diagnosis: string | null
  notes: string | null
}
