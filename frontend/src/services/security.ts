import api from '@/services/api'
import type { ListQuery, Paginated } from '@/types/common'
import type {
  PermissionCatalog,
  RoleItem,
  RolePayload,
  UserItem,
  UserPayload,
} from '@/types/security'

/** Cliente API del módulo Seguridad. */
export const securityService = {
  // --- Usuarios ---
  listUsers(query: ListQuery): Promise<Paginated<UserItem>> {
    return api.get('/security/users', { params: query }).then((r) => r.data)
  },
  createUser(payload: UserPayload): Promise<UserItem> {
    return api.post('/security/users', payload).then((r) => r.data)
  },
  updateUser(id: number, payload: UserPayload): Promise<UserItem> {
    return api.put(`/security/users/${id}`, payload).then((r) => r.data)
  },
  deleteUser(id: number): Promise<void> {
    return api.delete(`/security/users/${id}`).then(() => undefined)
  },

  // --- Roles ---
  listRoles(): Promise<RoleItem[]> {
    return api.get('/security/roles').then((r) => r.data.data)
  },
  createRole(payload: RolePayload): Promise<RoleItem> {
    return api.post('/security/roles', payload).then((r) => r.data)
  },
  updateRole(id: number, payload: RolePayload): Promise<RoleItem> {
    return api.put(`/security/roles/${id}`, payload).then((r) => r.data)
  },
  deleteRole(id: number): Promise<void> {
    return api.delete(`/security/roles/${id}`).then(() => undefined)
  },

  // --- Permisos ---
  listPermissions(): Promise<PermissionCatalog> {
    return api.get('/security/permissions').then((r) => r.data.data)
  },
}
