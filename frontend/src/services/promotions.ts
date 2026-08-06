import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type { ApplicablePromotions, PromotionItem, PromotionPayload } from '@/types/promotions'

/** Cliente API de promociones (Adición A5). */
export const promotionService = {
  list(query: Partial<ListQuery> = {}): Promise<Paginated<PromotionItem>> {
    return api.get('/promotions', { params: query }).then((r) => r.data)
  },
  get(id: number): Promise<PromotionItem> {
    return api.get(`/promotions/${id}`).then((r) => r.data)
  },
  create(payload: PromotionPayload): Promise<PromotionItem> {
    return api.post('/promotions', payload).then((r) => r.data)
  },
  update(id: number, payload: PromotionPayload): Promise<PromotionItem> {
    return api.put(`/promotions/${id}`, payload).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/promotions/${id}`).then(() => undefined)
  },

  /** Promociones aplicables a un producto (para prellenado en Ventas). */
  applicable(subjectType: string, subjectId: number, date?: string): Promise<ApplicablePromotions> {
    return api.get('/promotions/applicable', { params: { subjectType, subjectId, date } }).then((r) => r.data)
  },
}
