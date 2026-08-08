export interface InvoiceDocument {
  id: number
  saleId: number
  saleNumber: string
  docType: '01' | '03' | '07' | '08'
  docTypeName: string
  fullNumber: string
  issueDate: string
  customerName: string
  customerDocument: string
  subtotal: string
  igv: string
  total: string
  status: 'PENDIENTE' | 'ACEPTADO' | 'RECHAZADO'
  errorMessage: string | null
  hash?: string | null
  qrData?: string | null
  cdr?: string | null
  xml?: string | null
  // Enlaces oficiales que hospeda el proveedor (NubeFact)
  pdfUrl?: string | null
  xmlUrl?: string | null
  cdrUrl?: string | null
  // Detalle enriquecido para impresión
  discountTotal?: string
  customerAddress?: string | null
  igvRate?: number
  company?: InvoiceCompany
  items?: InvoiceLine[]
}

export interface BankAccount {
  name: string
  account: string
  cci: string
}

export interface InvoiceCompany {
  name: string
  tradeName: string
  ruc: string
  address: string
  department?: string
  province?: string
  district?: string
  phone: string
  email: string
  logo?: string | null
  banks?: BankAccount[]
}

export interface InvoiceLine {
  code?: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
  lineTotal: string
}
