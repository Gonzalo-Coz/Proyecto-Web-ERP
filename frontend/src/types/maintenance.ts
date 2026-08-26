export interface MaintenancePlanModel {
  id: number
  model: string
  kmIntervals: number[]
}

export interface MaintenancePlanPart {
  category: string
  code: string
  description: string
  unit: string
  quantity: number
  sparePartId: number | null
  internalCode: string | null
  salePrice: string | null
  stock: number | null
  inInventory: boolean
}

export interface MaintenancePlanActivity {
  system: string
  activity: string
  action: string
}

export interface MaintenancePlanServiceDetail {
  id: number
  model: string
  km: number
  labor: { hours: number | null; cost: number | null; free?: boolean } | null
  activities: MaintenancePlanActivity[]
  parts: MaintenancePlanPart[]
  legend: Record<string, string>
}
