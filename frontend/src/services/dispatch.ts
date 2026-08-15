import api from '@/services/api'
import type { PageMeta } from '@/types/common'
import type { DispatchGuideItem, DispatchGuidePayload } from '@/types/dispatch'

export const dispatchService = {
  list(query: { page: number; perPage: number; search: string; status: string }): Promise<{ data: DispatchGuideItem[]; meta: PageMeta }> {
    return api.get('/dispatch-guides', { params: query }).then((r) => r.data)
  },
  get(id: number): Promise<DispatchGuideItem> {
    return api.get(`/dispatch-guides/${id}`).then((r) => r.data)
  },
  create(payload: DispatchGuidePayload): Promise<DispatchGuideItem> {
    return api.post('/dispatch-guides', payload).then((r) => r.data)
  },
}
