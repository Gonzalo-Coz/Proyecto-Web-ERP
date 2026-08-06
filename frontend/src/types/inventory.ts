export interface SparePartItem {
  id: number
  internalCode: string
  partCode: string
  barcode: string | null
  description: string
  brandId: number | null
  brandName: string | null
  categoryId: number | null
  categoryName: string | null
  unitOfMeasure: string
  compatibleModelIds: number[]
  compatibleModelNames: string[]
  stock: number
  minStock: number
  maxStock: number | null
  purchasePrice: string | null
  salePrice: string | null
  location: string | null
  lastPurchaseAt: string | null
  isLowStock: boolean
  isOutOfStock: boolean
  isActive: boolean
}

export interface SparePartPayload {
  internalCode: string
  partCode: string
  barcode: string | null
  description: string
  brandId: number | null
  categoryId: number | null
  unitOfMeasure: string
  compatibleModelIds: number[]
  minStock: number
  maxStock: number | null
  purchasePrice: number | null
  salePrice: number | null
  location: string | null
  isActive: boolean
  /** Motivo del cambio de precio de venta (Adición A3); opcional. */
  priceChangeReason?: string | null
}

export interface KardexEntry {
  id: number
  movementType: string
  quantity: number
  unitCost: string | null
  balanceAfter: number
  reference: string | null
  notes: string | null
  username: string | null
  createdAt: string
}
