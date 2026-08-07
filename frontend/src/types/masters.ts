/** Tipos de los módulos maestros: Clientes y Proveedores. */

export const DOCUMENT_TYPES = ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO'] as const
export type DocumentType = (typeof DOCUMENT_TYPES)[number]

/** Tipo de cliente administrable con su % de descuento por defecto. */
export interface CustomerTypeItem {
  id: number
  name: string
  discountPercent: number
  isActive: boolean
}

export type CustomerTypePayload = Omit<CustomerTypeItem, 'id'>

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
  /** Lista de precios asignada (Adición A4). */
  priceListId: number | null
  priceListName: string | null
  /** Tipo de cliente (FK) y su % de descuento por defecto. */
  customerTypeId: number | null
  customerTypeLabel: string | null
  discountPercent: number
  isLegalEntity: boolean
  isActive: boolean
  createdAt: string | null
}

export type CustomerPayload = Omit<
  CustomerItem,
  'id' | 'isLegalEntity' | 'createdAt' | 'priceListName' | 'customerTypeLabel' | 'discountPercent'
>


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
