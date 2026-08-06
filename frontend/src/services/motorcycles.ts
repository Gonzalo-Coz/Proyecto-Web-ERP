import api from '@/services/api'
import { downloadTemplate, runImport } from '@/services/importHelpers'
import type { ListQuery, Paginated } from '@/types/common'
import type { ImportResult } from '@/types/import'
import type { ModelItem, ModelPayload, UnitItem, UnitPayload, UnitStatus } from '@/types/motorcycles'

export const modelService = {
  list(query: ListQuery): Promise<Paginated<ModelItem>> {
    return api.get('/motorcycles/models', { params: query }).then((r) => r.data)
  },
  create(payload: ModelPayload): Promise<ModelItem> {
    return api.post('/motorcycles/models', payload).then((r) => r.data)
  },
  update(id: number, payload: ModelPayload): Promise<ModelItem> {
    return api.put(`/motorcycles/models/${id}`, payload).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/motorcycles/models/${id}`).then(() => undefined)
  },
}

export const unitService = {
  list(query: ListQuery & { status?: string }): Promise<Paginated<UnitItem>> {
    return api.get('/motorcycles/units', { params: query }).then((r) => r.data)
  },
  create(payload: UnitPayload): Promise<UnitItem> {
    return api.post('/motorcycles/units', payload).then((r) => r.data)
  },
  update(id: number, payload: UnitPayload): Promise<UnitItem> {
    return api.put(`/motorcycles/units/${id}`, payload).then((r) => r.data)
  },
  changeStatus(id: number, status: UnitStatus): Promise<UnitItem> {
    return api.patch(`/motorcycles/units/${id}/status`, { status }).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/motorcycles/units/${id}`).then(() => undefined)
  },
  downloadImportTemplate(): Promise<void> {
    return downloadTemplate('/motorcycles/units/import/template', 'plantilla_motos_YIGM.csv')
  },
  importFile(file: File, dryRun: boolean): Promise<ImportResult> {
    return runImport('/motorcycles/units/import', file, dryRun)
  },
}
