import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type { SalePayload, SaleSummary } from '@/types/sales'

export const saleService = {
  list(query: ListQuery & { status?: string; customerId?: number; pending?: boolean }): Promise<Paginated<SaleSummary>> {
    return api.get('/sales', { params: query }).then((r) => r.data)
  },
  get(id: number): Promise<SaleSummary> {
    return api.get(`/sales/${id}`).then((r) => r.data)
  },
  /** IDs de motos que el cliente compró (para autocompletar la recepción de taller). */
  customerUnits(customerId: number): Promise<number[]> {
    return api.get(`/sales/customer-units/${customerId}`).then((r) => r.data)
  },
  create(payload: SalePayload): Promise<SaleSummary> {
    return api.post('/sales', payload).then((r) => r.data)
  },
  update(id: number, payload: SalePayload): Promise<SaleSummary> {
    return api.put(`/sales/${id}`, payload).then((r) => r.data)
  },
  reserve(id: number, expiresAt: string | null): Promise<SaleSummary> {
    return api.post(`/sales/${id}/reserve`, { expiresAt }).then((r) => r.data)
  },
  complete(id: number): Promise<SaleSummary> {
    return api.post(`/sales/${id}/complete`).then((r) => r.data)
  },
  addPayment(id: number, amount: number, paymentMethodId: number | null, reference: string | null): Promise<SaleSummary> {
    return api.post(`/sales/${id}/payments`, { amount, paymentMethodId, reference }).then((r) => r.data)
  },
  cancel(id: number): Promise<SaleSummary> {
    return api.post(`/sales/${id}/cancel`).then((r) => r.data)
  },
}
