/** Promoción (Adición A5). */
export interface PromotionItem {
  id: number
  code: string
  name: string
  type: 'DISCOUNT' | 'BONUS'
  discountPercent: string | null
  scopeType: 'ALL' | 'BRAND' | 'CATEGORY' | 'MODEL'
  scopeRefId: number | null
  scopeLabel: string
  bonusSubjectType: string | null
  bonusSubjectId: number | null
  bonusLabel: string | null
  bonusQuantity: number | null
  startDate: string
  endDate: string
  isActive: boolean
}

export interface PromotionPayload {
  code: string
  name: string
  type: 'DISCOUNT' | 'BONUS'
  discountPercent: number | null
  scopeType: 'ALL' | 'BRAND' | 'CATEGORY' | 'MODEL'
  scopeRefId: number | null
  bonusSubjectType: string | null
  bonusSubjectId: number | null
  bonusQuantity: number | null
  startDate: string
  endDate: string
  isActive: boolean
}

/** Bonificación aplicable devuelta por /promotions/applicable. */
export interface ApplicableBonus {
  promotion: string
  subjectType: string
  subjectId: number
  label: string
  quantity: number
}

/** Resultado de promociones aplicables a un producto. */
export interface ApplicablePromotions {
  discountPercent: number
  bonuses: ApplicableBonus[]
}
