import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type { CustomerItem, CustomerPayload, SupplierItem, SupplierPayload } from '@/types/masters'

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
