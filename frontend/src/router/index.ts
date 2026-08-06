import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  // Corrección de navegación: toda vista nueva inicia SIEMPRE desde arriba,
  // aunque el usuario haya hecho scroll en la vista anterior.
  scrollBehavior(to, from, savedPosition) {
    return savedPosition ?? { left: 0, top: 0 }
  },
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
      path: '/reports',
      name: 'reports',
      component: () => import('@/views/reports/ReportsView.vue'),
      meta: { title: 'Reportes', permission: 'reports.main.view' },
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
      path: '/motorcycles/models',
      name: 'moto-models',
      component: () => import('@/views/motorcycles/ModelsView.vue'),
      meta: { title: 'Modelos de Motocicleta', permission: 'motorcycles.models.view' },
    },
    {
      path: '/motorcycles/units',
      name: 'moto-units',
      component: () => import('@/views/motorcycles/UnitsView.vue'),
      meta: { title: 'Unidades de Motocicleta', permission: 'motorcycles.units.view' },
    },
    {
      path: '/sales',
      name: 'sales',
      component: () => import('@/views/sales/SalesView.vue'),
      meta: { title: 'Ventas', permission: 'sales.list.view' },
    },
    {
      path: '/promotions',
      name: 'promotions',
      component: () => import('@/views/sales/PromotionsView.vue'),
      meta: { title: 'Promociones', permission: 'sales.promotions.view' },
    },
    {
      path: '/workshop',
      name: 'workshop',
      component: () => import('@/views/workshop/WorkshopView.vue'),
      meta: { title: 'Taller', permission: 'workshop.orders.view' },
    },
    {
      path: '/invoicing',
      name: 'invoicing',
      component: () => import('@/views/invoicing/InvoicesView.vue'),
      meta: { title: 'Comprobantes Electrónicos', permission: 'invoicing.documents.view' },
    },
    {
      path: '/cash',
      name: 'cash',
      component: () => import('@/views/cash/CashView.vue'),
      meta: { title: 'Caja', permission: 'cash.sessions.view' },
    },
    {
      path: '/payments/transactions',
      name: 'payments',
      component: () => import('@/views/payments/PaymentsView.vue'),
      meta: { title: 'Pasarela de Pago', permission: 'payments.gateway.view' },
    },
    {
      path: '/purchases',
      name: 'purchases',
      component: () => import('@/views/purchases/PurchasesView.vue'),
      meta: { title: 'Compras', permission: 'purchases.list.view' },
    },
    {
      path: '/inventory/spare-parts',
      name: 'spare-parts',
      component: () => import('@/views/inventory/SparePartsView.vue'),
      meta: { title: 'Repuestos e Inventario', permission: 'inventory.spare_parts.view' },
    },
    {
      path: '/pricing/price-lists',
      name: 'price-lists',
      component: () => import('@/views/pricing/PriceListsView.vue'),
      meta: { title: 'Listas de Precios', permission: 'pricing.price_lists.view' },
    },
    {
      path: '/pricing/price-history',
      name: 'price-history',
      component: () => import('@/views/pricing/PriceHistoryView.vue'),
      meta: { title: 'Historial de Precios', permission: 'pricing.history.view' },
    },
    {
      path: '/settings/general',
      name: 'settings-general',
      component: () => import('@/views/settings/GeneralSettingsView.vue'),
      meta: { title: 'Configuración General', permission: 'settings.general.view' },
    },
    {
      path: '/settings/catalogs',
      name: 'settings-catalogs',
      component: () => import('@/views/catalogs/CatalogsView.vue'),
      meta: { title: 'Catálogos', permission: 'settings.catalogs.view' },
    },
    {
      path: '/account/profile',
      name: 'account-profile',
      component: () => import('@/views/account/ProfileView.vue'),
      // Autoservicio: cualquier usuario autenticado edita SU perfil (sin permiso).
      meta: { title: 'Mi Perfil' },
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

// Refuerzo del reinicio de scroll: cubre contenedores internos con overflow
router.afterEach(() => {
  window.scrollTo({ top: 0, left: 0 })
  document.querySelector('main')?.scrollTo({ top: 0, left: 0 })
})

export default router
