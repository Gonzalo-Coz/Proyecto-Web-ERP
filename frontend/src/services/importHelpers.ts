import api from '@/services/api'
import type { ImportResult } from '@/types/import'

/** Descarga una plantilla CSV (blob) y dispara la descarga en el navegador. */
export function downloadTemplate(url: string, filename: string): Promise<void> {
  return api.get(url, { responseType: 'blob' }).then((r) => {
    const objectUrl = URL.createObjectURL(r.data as Blob)
    const a = document.createElement('a')
    a.href = objectUrl
    a.download = filename
    a.click()
    URL.revokeObjectURL(objectUrl)
  })
}

/** Sube el archivo de importación. dryRun=true → vista previa sin guardar. */
export function runImport(url: string, file: File, dryRun: boolean): Promise<ImportResult> {
  const form = new FormData()
  form.append('file', file)
  return api.post(url, form, { params: { dryRun: dryRun ? 1 : 0 } }).then((r) => r.data)
}
