import api from '@/services/api'
import type { Paginated } from '@/types/common'
import type { PaymentTransactionItem, PaymentTransactionPayload } from '@/types/payments'

/** Cliente API de transacciones de pasarela de pago (Adición A6). */
export const paymentService = {
  list(query: Record<string, unknown>): Promise<Paginated<PaymentTransactionItem>> {
    return api.get('/payments/transactions', { params: query }).then((r) => r.data)
  },
  register(payload: PaymentTransactionPayload): Promise<PaymentTransactionItem> {
    return api.post('/payments/transactions', payload).then((r) => r.data)
  },
  approve(id: number): Promise<PaymentTransactionItem> {
    return api.post(`/payments/transactions/${id}/approve`).then((r) => r.data)
  },
  reject(id: number): Promise<PaymentTransactionItem> {
    return api.post(`/payments/transactions/${id}/reject`).then((r) => r.data)
  },
}
