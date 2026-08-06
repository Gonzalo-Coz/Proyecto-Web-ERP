import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type { KardexEntry, SparePartItem, SparePartPayload } from '@/types/inventory'

export const sparePartService = {
  list(query: ListQuery & { compatibleModelId?: number; stockFilter?: string }): Promise<Paginated<SparePartItem>> {
    return api.get('/inventory/spare-parts', { params: query }).then((r) => r.data)
  },
  create(payload: SparePartPayload): Promise<SparePartItem> {
    return api.post('/inventory/spare-parts', payload).then((r) => r.data)
  },
  update(id: number, payload: SparePartPayload): Promise<SparePartItem> {
    return api.put(`/inventory/spare-parts/${id}`, payload).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/inventory/spare-parts/${id}`).then(() => undefined)
  },
  adjust(id: number, quantity: number, reason: string): Promise<SparePartItem> {
    return api.post(`/inventory/spare-parts/${id}/adjust`, { quantity, reason }).then((r) => r.data)
  },
  kardex(id: number, page = 1, perPage = 10): Promise<Paginated<KardexEntry>> {
    return api.get(`/inventory/spare-parts/${id}/kardex`, { params: { page, perPage } }).then((r) => r.data)
  },
  downloadImportTemplate(): Promise<void> {
    return api.get('/inventory/spare-parts/import/template', { responseType: 'blob' }).then((r) => {
      const url = URL.createObjectURL(r.data as Blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'plantilla_productos_YIGM.csv'
      a.click()
      URL.revokeObjectURL(url)
    })
  },
  importFile(file: File, dryRun: boolean): Promise<ImportResult> {
    const form = new FormData()
    form.append('file', file)
    return api
      .post('/inventory/spare-parts/import', form, { params: { dryRun: dryRun ? 1 : 0 } })
      .then((r) => r.data)
  },
}

export interface ImportRow {
  line: number
  internalCode: string
  partCode: string
  description: string
  status: 'create' | 'update' | 'error'
  message: string
}

export interface ImportResult {
  summary: { total: number; create: number; update: number; error: number }
  rows: ImportRow[]
  committed: boolean
}
