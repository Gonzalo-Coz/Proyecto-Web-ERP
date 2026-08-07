import api from '@/services/api'
import { downloadTemplate, runImport } from '@/services/importHelpers'
import type { ListQuery, Paginated } from '@/types/common'
import type { ImportResult } from '@/types/import'
import type {
  CustomerItem,
  CustomerPayload,
  CustomerTypeItem,
  CustomerTypePayload,
  SupplierItem,
  SupplierPayload,
} from '@/types/masters'

export const customerTypeService = {
  list(): Promise<CustomerTypeItem[]> {
    return api.get('/customer-types').then((r) => r.data.data)
  },
  create(payload: CustomerTypePayload): Promise<CustomerTypeItem> {
    return api.post('/customer-types', payload).then((r) => r.data)
  },
  update(id: number, payload: CustomerTypePayload): Promise<CustomerTypeItem> {
    return api.put(`/customer-types/${id}`, payload).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/customer-types/${id}`).then(() => undefined)
  },
}

export const customerService = {
  list(query: ListQuery): Promise<Paginated<CustomerItem>> {
    return api.get('/customers', { params: query }).then((r) => r.data)
  },
  create(payload: CustomerPayload): Promise<CustomerItem> {
    return api.post('/customers', payload).then((r) => r.data)
  },
  update(id: number, payload: CustomerPayload): Promise<CustomerItem> {
    return api.put(`/customers/${id}`, payload).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/customers/${id}`).then(() => undefined)
  },
  /** Cliente genérico "Público General" para la boleta simple (sin datos). */
  generic(): Promise<CustomerItem> {
    return api.get('/customers/generic').then((r) => r.data)
  },
  downloadImportTemplate(): Promise<void> {
    return downloadTemplate('/customers/import/template', 'plantilla_clientes_YIGM.csv')
  },
  importFile(file: File, dryRun: boolean): Promise<ImportResult> {
    return runImport('/customers/import', file, dryRun)
  },
}

export const supplierService = {
  list(query: ListQuery): Promise<Paginated<SupplierItem>> {
    return api.get('/suppliers', { params: query }).then((r) => r.data)
  },
  create(payload: SupplierPayload): Promise<SupplierItem> {
    return api.post('/suppliers', payload).then((r) => r.data)
  },
  update(id: number, payload: SupplierPayload): Promise<SupplierItem> {
    return api.put(`/suppliers/${id}`, payload).then((r) => r.data)
  },
  remove(id: number): Promise<void> {
    return api.delete(`/suppliers/${id}`).then(() => undefined)
  },
}
