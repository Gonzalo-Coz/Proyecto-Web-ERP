import api from '@/services/api'
import type { CatalogItem, CatalogItemPayload, CatalogType } from '@/types/catalogs'

export const catalogService = {
  list(type: CatalogType): Promise<CatalogItem[]> {
    return api.get(`/catalogs/${type}`).then((r) => r.data.data)
  },
  create(type: CatalogType, payload: CatalogItemPayload): Promise<CatalogItem> {
    return api.post(`/catalogs/${type}`, payload).then((r) => r.data)
  },
  update(type: CatalogType, id: number, payload: CatalogItemPayload): Promise<CatalogItem> {
    return api.put(`/catalogs/${type}/${id}`, payload).then((r) => r.data)
  },
  remove(type: CatalogType, id: number): Promise<void> {
    return api.delete(`/catalogs/${type}/${id}`).then(() => undefined)
  },
}
