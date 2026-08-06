import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type {
  PriceHistoryItem,
  PriceHistoryQuery,
  PriceListDetail,
  PriceListItem,
  PriceListPayload,
  PriceResolution,
} from '@/types/pricing'

/** Cliente API de precios: historial (A3) y listas + resolución (A4). */
export const pricingService = {
  // --- Historial de precios (A3) ---
  history(query: PriceHistoryQuery): Promise<Paginated<PriceHistoryItem>> {
    return api.get('/pricing/price-history', { params: query }).then((r) => r.data)
  },

  // --- Listas de precios (A4) ---
  listPriceLists(query: Partial<ListQuery> = {}): Promise<Paginated<PriceListItem>> {
    return api.get('/pricing/price-lists', { params: query }).then((r) => r.data)
  },
  getPriceList(id: number): Promise<PriceListDetail> {
    return api.get(`/pricing/price-lists/${id}`).then((r) => r.data)
  },
  createPriceList(payload: PriceListPayload): Promise<PriceListDetail> {
    return api.post('/pricing/price-lists', payload).then((r) => r.data)
  },
  updatePriceList(id: number, payload: PriceListPayload): Promise<PriceListDetail> {
    return api.put(`/pricing/price-lists/${id}`, payload).then((r) => r.data)
  },
  removePriceList(id: number): Promise<void> {
    return api.delete(`/pricing/price-lists/${id}`).then(() => undefined)
  },

  /** Resuelve el precio de venta de un producto para un cliente (A4). */
  resolve(subjectType: string, subjectId: number, customerId?: number): Promise<PriceResolution> {
    return api
      .get('/pricing/resolve', { params: { subjectType, subjectId, customerId } })
      .then((r) => r.data)
  },
}
