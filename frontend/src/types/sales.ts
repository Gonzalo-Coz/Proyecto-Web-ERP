export const SALE_STATUSES = ['COTIZACION', 'RESERVA', 'COMPLETADA', 'ANULADA'] as const
export type SaleStatus = (typeof SALE_STATUSES)[number]

export interface SaleLine {
  itemType: 'SPARE_PART' | 'MOTORCYCLE_UNIT' | 'SERVICE'
  sparePartId: number | null
  motorcycleUnitId: number | null
  description: string | null
  quantity: number
  unitPrice: number
  /** Único descuento del sistema: porcentaje por línea (admite decimales). */
  discountPercent: number
  /** UI: si el precio unitario está en US$ (se convierte a soles con el T.C.). */
  _usd?: boolean
}

export interface SaleItemDetail extends SaleLine {
  id: number
  lineTotal: string
}

export interface SalePaymentItem {
  id: number
  amount: string
  paymentMethodName: string
  reference: string | null
  username: string
  createdAt: string
}

export interface SaleSummary {
  id: number
  saleNumber: string
  saleDate: string
  customerId: number
  customerName: string
  customerDocument: string
  seller: string
  status: SaleStatus
  subtotal: string
  igv: string
  total: string
  globalDiscount: string
  totalDiscount: string
  discountAuthorizedBy: string | null
  discountAuthorizedAt: string | null
  paidAmount: string
  balance: string
  paymentStatus: 'PENDIENTE' | 'PARCIAL' | 'PAGADO'
  reservationExpiresAt: string | null
  completedAt: string | null
  notes: string | null
  igvIncluded?: boolean
  igvExempt?: boolean
  currency?: 'PEN' | 'USD'
  items?: SaleItemDetail[]
  payments?: SalePaymentItem[]
}

export interface SalePayload {
  customerId: number
  saleDate: string
  items: SaleLine[]
  complete: boolean
  notes: string | null
  igvIncluded?: boolean
  igvExempt?: boolean
  currency?: 'PEN' | 'USD'
}
