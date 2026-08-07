import api from '@/services/api'

export interface PublicCompany {
  name: string
  tradeName: string
  logoFullPath: string
  logoIconPath: string
}

/** Convierte una ruta relativa (uploads/..) en URL servible; '' si no hay. */
export function mediaUrl(path?: string | null): string {
  if (!path) return ''
  return path.startsWith('http') || path.startsWith('/') ? path : `/${path}`
}

export const companyService = {
  /** Identidad pública (sin autenticación): usada en login y cabecera. */
  publicInfo(): Promise<PublicCompany> {
    return api.get('/public/company').then((r) => r.data)
  },
  uploadLogo(kind: 'full' | 'icon', file: File): Promise<{ key: string; path: string }> {
    const fd = new FormData()
    fd.append('logo', file)
    fd.append('kind', kind)
    return api.post('/settings/logo', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data)
  },
  removeLogo(kind: 'full' | 'icon'): Promise<{ key: string; path: string }> {
    return api.delete('/settings/logo', { params: { kind } }).then((r) => r.data)
  },
}
