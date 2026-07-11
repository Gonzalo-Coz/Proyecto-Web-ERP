<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

function handleLogout(): void {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen bg-gray-100">
    <!-- Barra lateral: el menú se generará dinámicamente según permisos (§23.9) -->
    <aside class="hidden w-64 flex-col bg-primary-950 text-white md:flex">
      <div class="flex h-16 items-center border-b border-white/10 px-6">
        <span class="text-lg font-semibold tracking-wide">YIGM ERP</span>
      </div>
      <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
        <RouterLink
          :to="{ name: 'dashboard' }"
          class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
          active-class="bg-white/15 font-medium"
        >
          Dashboard
        </RouterLink>

        <template v-if="auth.can('customers.list.view') || auth.can('suppliers.list.view')">
          <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-white/40">Comercial</p>
          <RouterLink
            v-if="auth.can('customers.list.view')"
            :to="{ name: 'customers' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Clientes
          </RouterLink>
          <RouterLink
            v-if="auth.can('suppliers.list.view')"
            :to="{ name: 'suppliers' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Proveedores
          </RouterLink>
        </template>

        <template v-if="auth.can('motorcycles.models.view') || auth.can('motorcycles.units.view')">
          <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-white/40">Motocicletas</p>
          <RouterLink
            v-if="auth.can('motorcycles.models.view')"
            :to="{ name: 'moto-models' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Modelos
          </RouterLink>
          <RouterLink
            v-if="auth.can('motorcycles.units.view')"
            :to="{ name: 'moto-units' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Unidades
          </RouterLink>
        </template>

        <template v-if="auth.can('inventory.spare_parts.view')">
          <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-white/40">Inventario</p>
          <RouterLink
            :to="{ name: 'spare-parts' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Repuestos
          </RouterLink>
        </template>

        <template v-if="auth.can('settings.catalogs.view')">
          <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-white/40">Configuración</p>
          <RouterLink
            :to="{ name: 'settings-catalogs' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Catálogos
          </RouterLink>
        </template>

        <template v-if="auth.can('security.users.view') || auth.can('security.roles.view')">
          <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase text-white/40">Seguridad</p>
          <RouterLink
            v-if="auth.can('security.users.view')"
            :to="{ name: 'security-users' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Usuarios
          </RouterLink>
          <RouterLink
            v-if="auth.can('security.roles.view')"
            :to="{ name: 'security-roles' }"
            class="block rounded-lg px-3 py-2 transition hover:bg-white/10"
            active-class="bg-white/15 font-medium"
          >
            Roles y Permisos
          </RouterLink>
        </template>
        <!-- Los módulos de negocio se agregarán aquí conforme avancen las fases -->
      </nav>
    </aside>

    <div class="flex flex-1 flex-col">
      <header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-6">
        <h1 class="text-base font-semibold text-gray-800">
          {{ $route.meta.title }}
        </h1>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">{{ auth.user?.fullName ?? 'Usuario' }}</span>
          <button type="button" class="btn-secondary" @click="handleLogout">
            Cerrar sesión
          </button>
        </div>
      </header>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
