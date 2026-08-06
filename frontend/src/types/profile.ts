import type { AuthUser } from '@/types/auth'

/** Perfil devuelto por la API (extiende al usuario autenticado con metadatos). */
export interface Profile extends AuthUser {
  updatedAt?: string | null
}

/** Datos editables del propio perfil. */
export interface ProfilePayload {
  fullName: string
  email: string
  phone: string | null
}

/** Cambio de contraseña desde el perfil. */
export interface ChangePasswordPayload {
  currentPassword: string
  newPassword: string
}
