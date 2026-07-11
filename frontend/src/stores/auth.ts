import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'
import type { AuthUser } from '@/types/auth'

/**
 * Store de sesión y permisos.
 * NOTA: el login real se conectará al endpoint /auth/login cuando se
 * implemente el módulo de Seguridad en el backend (Fase 0, parte 2).
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(sessionStorage.getItem('yigm_token'))
  const user = ref<AuthUser | null>(null)

  const isAuthenticated = computed(() => token.value !== null)

  async function login(username: string, password: string): Promise<void> {
    const { data } = await api.post<{ token: string; user: AuthUser }>('/auth/login', {
      username,
      password,
    })
    token.value = data.token
    user.value = data.user
    sessionStorage.setItem('yigm_token', data.token)
  }

  function logout(): void {
    token.value = null
    user.value = null
    sessionStorage.removeItem('yigm_token')
  }

  /**
   * Verifica un permiso módulo/pantalla/acción (§23.9).
   * El comodín "*" corresponde a un rol superadministrador.
   */
  function can(permission: string): boolean {
    const permissions = user.value?.permissions ?? []
    return permissions.includes('*') || permissions.includes(permission)
  }

  return { token, user, isAuthenticated, login, logout, can }
})
