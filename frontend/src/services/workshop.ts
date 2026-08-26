import api from '@/services/api'
import type { Paginated } from '@/types/common'
import type { OrderStatus, ServiceOrderPayload, ServiceOrderSummary } from '@/types/workshop'

export const workshopService = {
  list(page = 1, perPage = 10, search = '', status = ''): Promise<Paginated<ServiceOrderSummary>> {
    return api.get('/workshop/orders', { params: { page, perPage, search, status } }).then((r) => r.data)
  },
  get(id: number): Promise<ServiceOrderSummary> {
    return api.get(`/workshop/orders/${id}`).then((r) => r.data)
  },
  create(payload: ServiceOrderPayload): Promise<ServiceOrderSummary> {
    return api.post('/workshop/orders', payload).then((r) => r.data)
  },
  addItem(
    id: number,
    item: { itemType: 'PART' | 'LABOR'; sparePartId?: number | null; description?: string; quantity: number; unitPrice: number },
  ): Promise<ServiceOrderSummary> {
    return api.post(`/workshop/orders/${id}/items`, item).then((r) => r.data)
  },
  removeItem(id: number, itemId: number): Promise<ServiceOrderSummary> {
    return api.delete(`/workshop/orders/${id}/items/${itemId}`).then((r) => r.data)
  },
  applyPlan(id: number, planId: number, km: number, sparePartIds?: number[]): Promise<ServiceOrderSummary> {
    return api.post(`/workshop/orders/${id}/maintenance-plan`, { planId, km, sparePartIds }).then((r) => r.data)
  },
  changeStatus(id: number, status: OrderStatus): Promise<ServiceOrderSummary> {
    return api.patch(`/workshop/orders/${id}/status`, { status }).then((r) => r.data)
  },
  invoice(id: number): Promise<ServiceOrderSummary> {
    return api.post(`/workshop/orders/${id}/invoice`).then((r) => r.data)
  },
}
