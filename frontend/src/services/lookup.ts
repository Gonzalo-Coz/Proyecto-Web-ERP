import api from '@/services/api'
import type { CompanyLookup, PersonLookup } from '@/types/lookup'

/**
 * Consultas de DNI/RUC (integración de APIs externas).
 * El frontend nunca habla con APISPERU: pasa por el backend desacoplado.
 */
export const lookupService = {
  dni(dni: string): Promise<PersonLookup> {
    return api.get(`/lookup/dni/${dni}`).then((r) => r.data)
  },
  ruc(ruc: string): Promise<CompanyLookup> {
    return api.get(`/lookup/ruc/${ruc}`).then((r) => r.data)
  },
}
