import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type { PurchaseItemSummary, PurchasePayload } from '@/types/purchases'

export const purchaseService = {
  list(query: ListQuery): Promise<Paginated<PurchaseItemSummary>> {
    return api.get('/purchases', { params: query }).then((r) => r.data)
  },
  get(id: number): Promise<PurchaseItemSummary> {
    return api.get(`/purchases/${id}`).then((r) => r.data)
  },
  create(payload: PurchasePayload): Promise<PurchaseItemSummary> {
    return api.post('/purchases', payload).then((r) => r.data)
  },
  cancel(id: number): Promise<PurchaseItemSummary> {
    return api.post(`/purchases/${id}/cancel`).then((r) => r.data)
  },
}
