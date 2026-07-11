export const CATALOG_TYPES = [
  { key: 'brands', label: 'Marcas' },
  { key: 'categories', label: 'Categorías' },
  { key: 'payment_methods', label: 'Métodos de Pago' },
  { key: 'banks', label: 'Bancos' },
] as const

export type CatalogType = (typeof CATALOG_TYPES)[number]['key']

export interface CatalogItem {
  id: number
  type: CatalogType
  name: string
  code: string | null
  isActive: boolean
}

export interface CatalogItemPayload {
  name: string
  code: string | null
  isActive: boolean
}
