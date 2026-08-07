export const UNIT_STATUSES = ['DISPONIBLE', 'RESERVADA', 'VENDIDA', 'EN_TALLER', 'GARANTIA', 'BAJA'] as const
export type UnitStatus = (typeof UNIT_STATUSES)[number]

/** Estados asignados por el sistema (no seleccionables manualmente). */
export const SYSTEM_STATUSES: UnitStatus[] = ['VENDIDA', 'EN_TALLER', 'GARANTIA']

export interface ModelItem {
  id: number
  brandId: number
  brandName: string
  model: string
  version: string | null
  modelYear: number
  engineCapacity: string | null
  engineType: string | null
  power: string | null
  fuelType: string | null
  transmission: string | null
  tankCapacity: string | null
  weight: string | null
  colors: string | null
  warrantyMonths: number | null
  referencePrice: string | null
  fullName: string
  isActive: boolean
}

export type ModelPayload = Omit<ModelItem, 'id' | 'brandName' | 'fullName' | 'referencePrice'> & {
  referencePrice: number | null
  /** Motivo del cambio de precio de referencia (Adición A3); opcional. */
  priceChangeReason?: string | null
}

export interface UnitItem {
  id: number
  internalCode: string
  vin: string
  engineNumber: string | null
  chassisNumber: string | null
  series: string | null
  modelId: number
  modelName: string
  modelYear: number
  manufactureYear: number | null
  color: string
  entryDate: string
  purchaseDate: string | null
  supplierId: number | null
  supplierName: string | null
  purchasePrice: string | null
  salePrice: string | null
  status: UnitStatus
  location: string | null
  notes: string | null
  duaNumber: string | null
  duaItem: string | null
}

export interface UnitPayload {
  /** Opcional: se autogenera (M-00001…) al crear si no se envía. */
  internalCode?: string
  vin: string
  modelId: number
  color: string
  engineNumber: string | null
  chassisNumber: string | null
  series: string | null
  manufactureYear: number | null
  entryDate: string | null
  purchaseDate: string | null
  supplierId: number | null
  purchasePrice: number | null
  salePrice: number | null
  location: string | null
  notes: string | null
  duaNumber: string | null
  duaItem: string | null
}
