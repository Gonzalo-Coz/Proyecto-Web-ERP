/** Usuario autenticado devuelto por la API. */
export interface AuthUser {
  id: number
  username: string
  fullName: string
  email: string
  phone?: string | null
  /** URL pública de la fotografía de perfil (o null si no tiene). */
  avatarUrl?: string | null
  roles: string[]
  /** Permisos en formato "modulo.pantalla.accion" (§23.9). */
  permissions: string[]
}
