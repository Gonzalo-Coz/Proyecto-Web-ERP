/** Usuario autenticado devuelto por la API. */
export interface AuthUser {
  id: number
  username: string
  fullName: string
  email: string
  roles: string[]
  /** Permisos en formato "modulo.pantalla.accion" (§23.9). */
  permissions: string[]
}
