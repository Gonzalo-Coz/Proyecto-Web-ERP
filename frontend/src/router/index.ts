import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { public: true, title: 'Iniciar Sesión' },
    },
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/dashboard/DashboardView.vue'),
      meta: { title: 'Dashboard' },
    },
    {
      path: '/customers',
      name: 'customers',
      component: () => import('@/views/customers/CustomersView.vue'),
      meta: { title: 'Clientes', permission: 'customers.list.view' },
    },
    {
      path: '/suppliers',
      name: 'suppliers',
      component: () => import('@/views/suppliers/SuppliersView.vue'),
      meta: { title: 'Proveedores', permission: 'suppliers.list.view' },
    },
    {
      path: '/security/users',
      name: 'security-users',
      component: () => import('@/views/security/UsersView.vue'),
      meta: { title: 'Usuarios', permission: 'security.users.view' },
    },
    {
      path: '/security/roles',
      name: 'security-roles',
      component: () => import('@/views/security/RolesView.vue'),
      meta: { title: 'Roles y Permisos', permission: 'security.roles.view' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { public: true, title: 'Página no encontrada' },
    },
  ],
})

/**
 * Guard global (§23.9): autenticación en rutas no públicas y validación
 * de permiso por ruta (meta.permission). El backend siempre revalida.
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  document.title = to.meta.title ? `${to.meta.title} — YIGM ERP` : 'YIGM ERP'

  // Sesión restaurada tras F5: hay token pero aún no se cargó el usuario
  if (auth.token && !auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.logout()
    }
  }

  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.permission && !auth.can(to.meta.permission as string)) {
    return { name: 'dashboard' }
  }
})

export default router
