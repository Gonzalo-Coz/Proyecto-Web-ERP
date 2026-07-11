/** Tipos de los módulos maestros: Clientes y Proveedores. */

export const DOCUMENT_TYPES = ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO'] as const
export type DocumentType = (typeof DOCUMENT_TYPES)[number]

export interface CustomerItem {
  id: number
  documentType: DocumentType
  documentNumber: string
  name: string
  tradeName: string | null
  address: string | null
  district: string | null
  province: string | null
  department: string | null
  phone: string | null
  mobile: string | null
  email: string | null
  isLegalEntity: boolean
  isActive: boolean
  createdAt: string | null
}

export type CustomerPayload = Omit<CustomerItem, 'id' | 'isLegalEntity' | 'createdAt'>

export interface SupplierItem {
  id: number
  ruc: string
  businessName: string
  tradeName: string | null
  address: string | null
  city: string | null
  phone: string | null
  email: string | null
  contactPerson: string | null
  isActive: boolean
  createdAt: string | null
}

export type SupplierPayload = Omit<SupplierItem, 'id' | 'createdAt'>
