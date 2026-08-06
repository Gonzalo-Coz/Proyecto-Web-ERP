import api from '@/services/api'
import type { Paginated } from '@/types/common'
import type { CashMovementItem, CashSessionItem } from '@/types/cash'

export const cashService = {
  current(): Promise<{ session: CashSessionItem | null }> {
    return api.get('/cash/current').then((r) => r.data)
  },
  sessions(page = 1, perPage = 10): Promise<Paginated<CashSessionItem>> {
    return api.get('/cash/sessions', { params: { page, perPage } }).then((r) => r.data)
  },
  open(openingAmount: number, notes: string | null): Promise<CashSessionItem> {
    return api.post('/cash/sessions/open', { openingAmount, notes }).then((r) => r.data)
  },
  close(id: number, countedAmount: number, notes: string | null): Promise<CashSessionItem> {
    return api.post(`/cash/sessions/${id}/close`, { countedAmount, notes }).then((r) => r.data)
  },
  movements(sessionId: number): Promise<CashMovementItem[]> {
    return api.get(`/cash/sessions/${sessionId}/movements`).then((r) => r.data.data)
  },
  addMovement(movementType: 'INGRESO' | 'EGRESO', amount: number, paymentMethodId: number | null, concept: string): Promise<void> {
    return api.post('/cash/movements', { movementType, amount, paymentMethodId, concept }).then(() => undefined)
  },
}
