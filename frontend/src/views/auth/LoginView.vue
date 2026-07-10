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
  <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-950 to-primary-800 p-4">
    <div class="card w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">YIGM ERP</h1>
        <p class="mt-1 text-sm text-gray-500">Yamaha Integral Global Motors</p>
      </div>

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <div>
          <label for="username" class="form-label">Usuario</label>
          <input
            id="username"
            v-model="username"
            type="text"
            class="form-input"
            autocomplete="username"
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

        <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Ingresando…' : 'Iniciar Sesión' }}
        </button>
      </form>
    </div>
  </div>
</template>
