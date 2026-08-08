export interface RoleSummary {
  id: number
  code: string
  name: string
}

export interface UserItem {
  id: number
  username: string
  email: string | null
  phone: string | null
  fullName: string
  isActive: boolean
  roles: RoleSummary[]
  createdAt: string | null
}

export interface UserPayload {
  username: string
  email: string | null
  phone?: string | null
  fullName: string
  password?: string | null
  roleIds: number[]
  isActive: boolean
}

export interface RoleItem {
  id: number
  code: string
  name: string
  description: string | null
  isSuperAdmin: boolean
  isActive: boolean
  maxDiscountPercent: number | null
  permissionCodes: string[]
}

export interface RolePayload {
  code: string
  name: string
  description: string | null
  permissionCodes: string[]
  isActive: boolean
  maxDiscountPercent?: number | null
}

export interface PermissionItem {
  code: string
  screen: string
  action: string
  name: string
}

/** Catálogo agrupado por módulo, como lo devuelve la API. */
export type PermissionCatalog = Record<string, PermissionItem[]>
