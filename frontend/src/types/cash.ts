export interface CashSessionItem {
  id: number
  sessionNumber: string
  openedBy: string
  openedAt: string
  openingAmount: string
  closedBy: string | null
  closedAt: string | null
  countedAmount: string | null
  expectedAmount: string | null
  difference: string | null
  status: 'ABIERTA' | 'CERRADA'
  notes: string | null
  totalIncome: string
  totalExpense: string
  liveExpectedCash: string
}

export interface CashMovementItem {
  id: number
  movementType: 'INGRESO' | 'EGRESO'
  amount: string
  paymentMethodName: string
  concept: string
  reference: string | null
  username: string
  createdAt: string
}
