<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
const loading = ref(false)
const errorMessage = ref('')

/** Activos de marca (frontend/public/brand/). Respaldo elegante si faltan. */
const igmLogoOk = ref(true)
const yamahaLogoOk = ref(true)

async function handleSubmit(): Promise<void> {
  errorMessage.value = ''
  loading.value = true
  try {
    await auth.login(username.value, password.value)
    const redirect = (route.query.redirect as string) || { name: 'dashboard' }
    await router.push(redirect)
  } catch {
    errorMessage.value = 'Credenciales incorrectas o servidor no disponible.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen bg-slate-100">
    <!-- ============ Panel de marca (marino profundo) ============ -->
    <div class="relative hidden w-[52%] flex-col justify-between overflow-hidden bg-sidebar p-12 text-white lg:flex">
      <!-- Fotografía de catálogo integrada: presencia alta, texto siempre legible -->
      <div
        class="pointer-events-none absolute inset-0 bg-cover bg-[position:70%_center] opacity-70"
        style="background-image: url('/brand/login-bg.jpg')"
      />
      <!-- Degradado lateral: ancla el texto a la izquierda sin apagar la moto -->
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-sidebar via-sidebar/70 to-sidebar/20" />
      <!-- Cierre inferior para la franja de marca -->
      <div class="pointer-events-none absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-sidebar to-transparent" />
      <!-- Viñeta sutil -->
      <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_30%,rgba(10,14,25,0.4)_100%)]" />
      <!-- Halos de luz -->
      <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-primary-500/20 blur-3xl" />
      <div class="pointer-events-none absolute -bottom-44 -left-24 h-[28rem] w-[28rem] rounded-full bg-accent/10 blur-3xl" />

      <!-- Marca superior -->
      <div class="relative flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent text-base font-extrabold text-white shadow-lg">
          Y
        </div>
        <div>
          <p class="text-lg font-bold tracking-wide">YIGM ERP</p>
          <p class="text-[10px] uppercase tracking-[0.25em] text-white/40">Sistema de Gestión Integral</p>
        </div>
      </div>

      <!-- Mensaje de producto -->
      <div class="relative max-w-md">
        <p class="mb-3 inline-block rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-white/60 backdrop-blur">
          Distribuidor oficial de Yamaha
        </p>
        <h2 class="text-4xl font-bold leading-[1.15] tracking-tight">
          Toda tu operación,<br />
          <span class="text-accent-soft">en una sola plataforma.</span>
        </h2>
        <p class="mt-4 text-sm leading-relaxed text-white/55">
          Ventas, inventario, taller, caja y facturación electrónica trabajando
          en tiempo real, con trazabilidad completa de cada operación.
        </p>
      </div>

      <!-- Franja Yamaha: chip blanco integrado, respeta el logo original -->
      <div class="relative flex items-center justify-between">
        <p class="text-xs text-white/30">Integra Global Motors · Uso interno</p>
        <div
          v-if="yamahaLogoOk"
          class="flex items-center rounded-xl bg-white px-4 py-2 shadow-lg ring-1 ring-white/20"
        >
          <img
            src="/brand/logo-yamaha.png"
            alt="Yamaha — Revs Your Heart"
            class="h-9 w-auto object-contain"
            @error="yamahaLogoOk = false"
          />
        </div>
      </div>
    </div>

    <!-- ============ Panel de acceso (claro) ============ -->
    <div class="relative flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-sm">
        <!-- Emblema IGM sobre superficie clara: el logo luce en su color original -->
        <div class="mb-8 flex justify-center">
          <img
            v-if="igmLogoOk"
            src="/brand/logo-igm.png"
            alt="Integra Global Motors — Distribuidor oficial de Yamaha"
            class="h-36 w-auto object-contain drop-shadow-sm"
            @error="igmLogoOk = false"
          />
          <div v-else class="flex flex-col items-center gap-2">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-accent text-2xl font-extrabold text-white shadow-lg">
              Y
            </div>
            <p class="text-lg font-bold text-slate-900">Integra Global Motors</p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-8 shadow-card">
          <h1 class="text-xl font-bold tracking-tight text-slate-900">Bienvenido</h1>
          <p class="mb-6 mt-1 text-sm text-slate-500">Ingresa tus credenciales para continuar.</p>

          <form class="space-y-5" @submit.prevent="handleSubmit">
            <div>
              <label for="username" class="form-label">Usuario</label>
              <input
                id="username"
                v-model="username"
                type="text"
                class="form-input"
                autocomplete="username"
                autofocus
                required
              />
            </div>

            <div>
              <label for="password" class="form-label">Contraseña</label>
              <input
                id="password"
                v-model="password"
                type="password"
                class="form-input"
                autocomplete="current-password"
                required
              />
            </div>

            <p v-if="errorMessage" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
              {{ errorMessage }}
            </p>

            <button type="submit" class="btn-primary w-full !py-2.5" :disabled="loading">
              {{ loading ? 'Ingresando…' : 'Iniciar Sesión' }}
            </button>
          </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
          Acceso restringido al personal autorizado.
        </p>
      </div>
    </div>
  </div>
</template>
