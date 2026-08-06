export const PAYMENT_METHODS = ['YAPE', 'PLIN', 'CARD', 'TRANSFER', 'EFECTIVO', 'OTHER'] as const
export const PAYMENT_STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'VOIDED'] as const

/** Transacción de pasarela de pago (Adición A6). */
export interface PaymentTransactionItem {
  id: number
  saleId: number | null
  saleNumber: string | null
  customerLabel: string | null
  method: string
  amount: string
  currency: string
  status: string
  gateway: string
  operationNumber: string | null
  notes: string | null
  createdBy: string | null
  validatedBy: string | null
  validatedAt: string | null
  createdAt: string | null
}

export interface PaymentTransactionPayload {
  method: string
  amount: number
  operationNumber: string | null
  saleId: number | null
  saleNumber: string | null
  customerLabel: string | null
  notes: string | null
}
