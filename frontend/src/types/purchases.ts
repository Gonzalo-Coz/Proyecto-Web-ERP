export const PURCHASE_DOCUMENT_TYPES = ['FACTURA', 'BOLETA', 'GUIA', 'OTRO'] as const

export interface PurchaseLine {
  itemType: 'SPARE_PART' | 'MOTORCYCLE_UNIT'
  sparePartId: number | null
  motorcycleUnitId: number | null
  quantity: number
  unitPrice: number
  discount: number
}

export interface PurchaseItemDetail extends PurchaseLine {
  id: number
  description: string
  lineTotal: string
}

export interface PurchaseItemSummary {
  id: number
  purchaseNumber: string
  purchaseDate: string
  supplierId: number
  supplierName: string
  documentType: string
  series: string | null
  documentNumber: string | null
  currency: string
  paymentMethodName: string | null
  subtotal: string
  igv: string
  total: string
  status: 'REGISTRADA' | 'ANULADA'
  notes: string | null
  items?: PurchaseItemDetail[]
}

export interface PurchasePayload {
  supplierId: number
  purchaseDate: string
  documentType: string
  items: PurchaseLine[]
  series: string | null
  documentNumber: string | null
  paymentMethodId: number | null
  notes: string | null
}
