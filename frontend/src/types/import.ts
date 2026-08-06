// Tipos compartidos de la carga masiva (Repuestos, Clientes, Unidades).

export interface ImportRow {
  line: number
  code: string
  label: string
  status: 'create' | 'update' | 'error'
  message: string
}

export interface ImportResult {
  summary: { total: number; create: number; update: number; error: number }
  rows: ImportRow[]
  committed: boolean
}
