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
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { public: true, title: 'Página no encontrada' },
    },
  ],
})

// Guard global: exige autenticación en rutas no públicas.
// La validación definitiva de permisos por módulo/pantalla/acción (§23.9)
// se implementará junto con el módulo de Seguridad.
router.beforeEach((to) => {
  const auth = useAuthStore()

  document.title = to.meta.title ? `${to.meta.title} — YIGM ERP` : 'YIGM ERP'

  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
