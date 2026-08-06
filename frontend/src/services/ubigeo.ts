import api from '@/services/api'
import type { UbigeoItem } from '@/types/ubigeo'

/** Ubicaciones del Perú en cascada, servidas por el backend (offline en LAN). */
export const ubigeoService = {
  departments(): Promise<UbigeoItem[]> {
    return api.get('/ubigeo/departments').then((r) => r.data)
  },
  provinces(departmentId: string): Promise<UbigeoItem[]> {
    return api.get(`/ubigeo/provinces/${departmentId}`).then((r) => r.data)
  },
  districts(provinceId: string): Promise<UbigeoItem[]> {
    return api.get(`/ubigeo/districts/${provinceId}`).then((r) => r.data)
  },
}
