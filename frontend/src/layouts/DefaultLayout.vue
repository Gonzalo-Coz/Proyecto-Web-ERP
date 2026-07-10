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
        <!-- Los módulos se agregarán aquí conforme avancen las fases -->
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
