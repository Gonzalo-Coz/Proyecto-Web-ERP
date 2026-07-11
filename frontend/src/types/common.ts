/** Tipos transversales de la API. */

export interface PageMeta {
  page: number
  perPage: number
  total: number
  totalPages: number
}

export interface Paginated<T> {
  data: T[]
  meta: PageMeta
}

/** Parámetros estándar de listado que aceptan todos los módulos. */
export interface ListQuery {
  page: number
  perPage: number
  search: string
  sort: string
  direction: 'asc' | 'desc'
}

export interface TableColumn {
  key: string
  label: string
  sortable?: boolean
}
