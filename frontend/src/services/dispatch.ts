import api from '@/services/api'
import type { PageMeta } from '@/types/common'
import type { DispatchGuideItem, DispatchGuidePayload, SaleDocumentOption } from '@/types/dispatch'

export const dispatchService = {
  list(query: { page: number; perPage: number; search: string; status: string }): Promise<{ data: DispatchGuideItem[]; meta: PageMeta }> {
    return api.get('/dispatch-guides', { params: query }).then((r) => r.data)
  },
  get(id: number): Promise<DispatchGuideItem> {
    return api.get(`/dispatch-guides/${id}`).then((r) => r.data)
  },
  saleDocuments(docType: string, search: string): Promise<SaleDocumentOption[]> {
    return api.get('/dispatch-guides/sale-documents', { params: { docType, search } }).then((r) => r.data)
  },
  create(payload: DispatchGuidePayload): Promise<DispatchGuideItem> {
    return api.post('/dispatch-guides', payload).then((r) => r.data)
  },
  update(id: number, payload: DispatchGuidePayload): Promise<DispatchGuideItem> {
    return api.put(`/dispatch-guides/${id}`, payload).then((r) => r.data)
  },
  annul(id: number, reason: string): Promise<DispatchGuideItem> {
    return api.post(`/dispatch-guides/${id}/annul`, { reason }).then((r) => r.data)
  },
  emit(id: number): Promise<DispatchGuideItem> {
    return api.post(`/dispatch-guides/${id}/emit`).then((r) => r.data)
  },
  consult(id: number): Promise<DispatchGuideItem> {
    return api.post(`/dispatch-guides/${id}/consult`).then((r) => r.data)
  },
}
