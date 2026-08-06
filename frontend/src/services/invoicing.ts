import api from '@/services/api'
import type { Paginated } from '@/types/common'
import type { InvoiceDocument } from '@/types/invoicing'

export const invoicingService = {
  list(page = 1, perPage = 10, search = '', status = ''): Promise<Paginated<InvoiceDocument>> {
    return api.get('/invoicing/documents', { params: { page, perPage, search, status } }).then((r) => r.data)
  },
  get(id: number): Promise<InvoiceDocument> {
    return api.get(`/invoicing/documents/${id}`).then((r) => r.data)
  },
  issue(saleId: number, docType: '01' | '03'): Promise<InvoiceDocument> {
    return api.post('/invoicing/documents', { saleId, docType }).then((r) => r.data)
  },
  resend(id: number): Promise<InvoiceDocument> {
    return api.post(`/invoicing/documents/${id}/resend`).then((r) => r.data)
  },
  consult(id: number): Promise<InvoiceDocument> {
    return api.post(`/invoicing/documents/${id}/consult`).then((r) => r.data)
  },
  downloadXml(id: number): Promise<void> {
    return api.get(`/invoicing/documents/${id}/xml`, { responseType: 'blob' }).then((r) => {
      const cd = String(r.headers['content-disposition'] ?? '')
      const filename = /filename="?([^"]+)"?/.exec(cd)?.[1] ?? `comprobante-${id}.xml`
      const url = URL.createObjectURL(r.data as Blob)
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      a.click()
      URL.revokeObjectURL(url)
    })
  },
}
