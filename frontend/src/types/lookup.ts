/** Respuesta de consulta por DNI (Integración APISPERU). */
export interface PersonLookup {
  dni: string
  nombres: string
  apellidoPaterno: string
  apellidoMaterno: string
  nombreCompleto: string
}

/** Respuesta de consulta por RUC. */
export interface CompanyLookup {
  ruc: string
  razonSocial: string
  nombreComercial: string | null
  estado: string | null
  condicion: string | null
  direccion: string | null
  departamento: string | null
  provincia: string | null
  distrito: string | null
  actividadEconomica: string | null
}

/** Respuesta de consulta vehicular por placa (datos técnicos). */
export interface VehicleLookup {
  placa: string
  marca: string
  modelo: string
  color: string
  serie: string
  motor: string
  vin: string
}
