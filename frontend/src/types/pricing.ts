/** Entrada del historial de precios (Adición A3). */
export interface PriceHistoryItem {
  id: number
  subjectType: string
  subjectTypeLabel: string
  subjectId: number
  subjectLabel: string
  oldPrice: string | null
  newPrice: string | null
  reason: string | null
  username: string | null
  createdAt: string
}

/** Filtros del reporte de historial de precios. */
export interface PriceHistoryQuery {
  page: number
  perPage: number
  subjectType?: string
  subjectId?: number
  search?: string
  from?: string
  to?: string
}

/** Lista de precios — fila de listado (Adición A4). */
export interface PriceListItem {
  id: number
  code: string
  name: string
  isDefault: boolean
  isActive: boolean
  itemCount: number
  createdAt: string | null
}

/** Línea de precio dentro de una lista. */
export interface PriceListLine {
  id?: number
  subjectType: string
  subjectId: number
  subjectLabel?: string
  price: number | string
}

/** Detalle de una lista con sus líneas. */
export interface PriceListDetail extends PriceListItem {
  items: PriceListLine[]
}

/** Payload de creación/edición de lista de precios. */
export interface PriceListPayload {
  code: string
  name: string
  isDefault: boolean
  isActive: boolean
  items: { subjectType: string; subjectId: number; price: number }[]
}

/** Resultado de la resolución de precio para una venta. */
export interface PriceResolution {
  price: string | null
  source: 'price_list' | 'base' | 'none'
}
