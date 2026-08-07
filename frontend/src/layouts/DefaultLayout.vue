<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

function handleLogout(): void {
  auth.logout()
  router.push({ name: 'login' })
}

interface MenuItem {
  name: string
  label: string
  permission: string | null
  icon: string
}

/** Menú declarativo con iconografía propia (trazos Heroicons). */
const MENU: { section: string; items: MenuItem[] }[] = [
  {
    section: 'Principal',
    items: [
      { name: 'dashboard', label: 'Dashboard', permission: null, icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
      { name: 'reports', label: 'Reportes', permission: 'reports.main.view', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    ],
  },
  {
    section: 'Comercial',
    items: [
      { name: 'sales', label: 'Ventas', permission: 'sales.list.view', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
      { name: 'invoicing', label: 'Comprobantes', permission: 'invoicing.documents.view', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
      { name: 'promotions', label: 'Promociones', permission: 'sales.promotions.view', icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z' },
      { name: 'customers', label: 'Clientes', permission: 'customers.list.view', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
      { name: 'suppliers', label: 'Proveedores', permission: 'suppliers.list.view', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    ],
  },
  {
    section: 'Motocicletas',
    items: [
      { name: 'moto-models', label: 'Modelos', permission: 'motorcycles.models.view', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
      { name: 'moto-units', label: 'Unidades', permission: 'motorcycles.units.view', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    ],
  },
  {
    section: 'Inventario',
    items: [
      { name: 'purchases', label: 'Compras', permission: 'purchases.list.view', icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z' },
      { name: 'spare-parts', label: 'Repuestos', permission: 'inventory.spare_parts.view', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z' },
      { name: 'price-lists', label: 'Listas de Precios', permission: 'pricing.price_lists.view', icon: 'M4 6h16M4 10h16M4 14h10M4 18h10M18 14l3 3-3 3m3-3h-7' },
      { name: 'price-history', label: 'Historial de Precios', permission: 'pricing.history.view', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    ],
  },
  {
    section: 'Servicio Técnico',
    items: [
      { name: 'workshop', label: 'Taller', permission: 'workshop.orders.view', icon: 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z' },
    ],
  },
  {
    section: 'Finanzas',
    items: [
      { name: 'cash', label: 'Caja', permission: 'cash.sessions.view', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
      { name: 'payments', label: 'Pasarela de Pago', permission: 'payments.gateway.view', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
    ],
  },
  {
    section: 'Administración',
    items: [
      { name: 'settings-general', label: 'Perfil de la Empresa', permission: 'settings.general.view', icon: 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75' },
      { name: 'settings-catalogs', label: 'Catálogos', permission: 'settings.catalogs.view', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
      { name: 'security-users', label: 'Usuarios', permission: 'security.users.view', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
      { name: 'security-roles', label: 'Roles y Permisos', permission: 'security.roles.view', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    ],
  },
]

function visibleItems(items: MenuItem[]): boolean {
  return items.some((i) => i.permission === null || auth.can(i.permission))
}

const initials = (): string =>
  (auth.user?.fullName ?? 'U')
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

const today = new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())

/** Emblema oficial (subido desde Perfil de la Empresa o el estático de respaldo). */
import { onMounted, ref } from 'vue'
import { companyService, mediaUrl } from '@/services/company'
const brandLogoOk = ref(true)
const brandIcon = ref('/brand/logo-full.png')
onMounted(async () => {
  try {
    const info = await companyService.publicInfo()
    if (info.logoFullPath) brandIcon.value = mediaUrl(info.logoFullPath)
  } catch {
    /* usa el logo por defecto */
  }
})
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-slate-100">
    <!-- Barra lateral -->
    <aside class="hidden w-64 shrink-0 flex-col bg-sidebar text-white md:flex">
      <div class="flex h-16 items-center border-b border-white/[0.08] px-4">
        <div
          v-if="brandLogoOk"
          class="flex h-11 w-full items-center justify-center overflow-hidden rounded-lg bg-white px-2 shadow"
        >
          <img
            :src="brandIcon"
            alt="Logo de la empresa"
            class="max-h-9 w-auto max-w-full object-contain"
            @error="brandLogoOk = false"
          />
        </div>
        <div v-else class="flex items-center gap-2">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent text-sm font-extrabold text-white shadow">Y</div>
          <p class="text-sm font-bold leading-tight tracking-wide">YIGM ERP</p>
        </div>
      </div>

      <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-5 text-sm">
        <template v-for="group in MENU" :key="group.section">
          <div v-if="visibleItems(group.items)">
            <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-white/30">
              {{ group.section }}
            </p>
            <div class="space-y-0.5">
              <template v-for="item in group.items" :key="item.name">
                <RouterLink
                  v-if="item.permission === null || auth.can(item.permission)"
                  :to="{ name: item.name }"
                  class="group relative flex items-center gap-3 rounded-lg px-3 py-2 text-white/65 transition hover:bg-white/[0.07] hover:text-white"
                  active-class="!bg-primary-700/40 !text-white font-semibold [&>span.marker]:opacity-100"
                >
                  <span class="marker absolute inset-y-1.5 left-0 w-[3px] rounded-full bg-accent-soft opacity-0 transition" />
                  <svg class="h-[18px] w-[18px] shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                  </svg>
                  <span class="truncate">{{ item.label }}</span>
                </RouterLink>
              </template>
            </div>
          </div>
        </template>
      </nav>

      <div class="border-t border-white/[0.08] px-5 py-3 text-[10px] leading-relaxed text-white/25">
        Yamaha Integral Global Motors
      </div>
    </aside>

    <!-- Contenido -->
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <header class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-6 shadow-sm backdrop-blur">
        <div class="min-w-0">
          <h1 class="truncate text-lg font-bold tracking-tight text-slate-900">
            {{ $route.meta.title }}
          </h1>
          <p class="text-[11px] capitalize text-slate-400">{{ today }}</p>
        </div>
        <div class="flex items-center gap-3">
          <RouterLink
            :to="{ name: 'account-profile' }"
            class="flex items-center gap-2.5 rounded-lg px-1.5 py-1 transition hover:bg-slate-100"
            title="Mi perfil"
          >
            <img
              v-if="auth.user?.avatarUrl"
              :src="auth.user.avatarUrl"
              alt="Mi perfil"
              class="h-9 w-9 rounded-full object-cover ring-2 ring-primary-100"
            />
            <div
              v-else
              class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-800 text-xs font-bold text-white ring-2 ring-primary-100"
            >
              {{ initials() }}
            </div>
            <div class="hidden leading-tight sm:block">
              <p class="text-sm font-semibold text-slate-800">{{ auth.user?.fullName ?? 'Usuario' }}</p>
              <p class="text-xs text-slate-400">{{ auth.user?.username }}</p>
            </div>
          </RouterLink>
          <button type="button" class="btn-ghost" @click="handleLogout">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Salir
          </button>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto p-6 lg:p-8">
        <div class="mx-auto w-full max-w-[1600px]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
