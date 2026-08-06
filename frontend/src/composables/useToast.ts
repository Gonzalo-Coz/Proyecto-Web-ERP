import { reactive } from 'vue'

/** Tipos de notificación del ERP. */
export type ToastType = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  type: ToastType
  message: string
}

// Estado único (singleton a nivel de módulo): un solo host lo renderiza.
const toasts = reactive<Toast[]>([])
let seq = 0

function push(type: ToastType, message: string, timeout = 4000): number {
  const id = ++seq
  toasts.push({ id, type, message })
  if (timeout > 0) {
    window.setTimeout(() => remove(id), timeout)
  }
  return id
}

function remove(id: number): void {
  const i = toasts.findIndex((t) => t.id === id)
  if (i !== -1) toasts.splice(i, 1)
}

/**
 * Sistema unificado de notificaciones (CONS-004). Uso:
 *   const toast = useToast()
 *   toast.success('Guardado correctamente')
 *   toast.error('No se pudo guardar')
 */
export function useToast() {
  return {
    toasts,
    remove,
    success: (message: string) => push('success', message),
    error: (message: string) => push('error', message),
    info: (message: string) => push('info', message),
  }
}
