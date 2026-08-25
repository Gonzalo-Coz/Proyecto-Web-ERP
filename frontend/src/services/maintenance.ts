import api from '@/services/api'
import type { MaintenancePlanModel, MaintenancePlanServiceDetail } from '@/types/maintenance'

/** Planes de mantenimiento por kilometraje (solo lectura). Alimenta el Taller. */
export const maintenanceService = {
  models(): Promise<MaintenancePlanModel[]> {
    return api.get('/maintenance-plans').then((r) => r.data)
  },
  service(id: number, km: number): Promise<MaintenancePlanServiceDetail> {
    return api.get(`/maintenance-plans/${id}/service`, { params: { km } }).then((r) => r.data)
  },
}
