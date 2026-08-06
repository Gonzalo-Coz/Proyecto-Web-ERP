<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import FormField from '@/components/ui/FormField.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const form = reactive<Record<string, string>>({})
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const errorMsg = ref('')

const FIELDS = [
  { key: 'company.name', label: 'Razón Social' },
  { key: 'company.trade_name', label: 'Nombre Comercial' },
  { key: 'company.ruc', label: 'RUC' },
  { key: 'company.address', label: 'Dirección' },
  { key: 'company.phone', label: 'Teléfono' },
  { key: 'company.email', label: 'Correo' },
  { key: 'tax.igv_rate', label: 'IGV (%)' },
  { key: 'sales.reservation_days', label: 'Vigencia de reservas (días)' },
]

async function load(): Promise<void> {
  loading.value = true
  const { data } = await api.get('/settings')
  Object.assign(form, data.data)
  loading.value = false
}

async function save(): Promise<void> {
  saving.value = true
  message.value = ''
  errorMsg.value = ''
  try {
    await api.put('/settings', { ...form })
    message.value = 'Configuración guardada correctamente.'
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo guardar la configuración.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <DefaultLayout>
    <div class="card max-w-2xl">
      <h2 class="mb-4 text-lg font-semibold text-gray-900">Datos de la Empresa y Parámetros</h2>
      <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>
      <form v-else class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField v-for="f in FIELDS" :key="f.key" :label="f.label">
            <input v-model="form[f.key]" class="form-input" :disabled="!auth.can('settings.general.edit')" />
          </FormField>
        </div>
        <p class="text-xs text-gray-500">
          El IGV se aplica a las nuevas ventas y compras. Los comprobantes ya emitidos no cambian (§19).
        </p>
        <p v-if="message" class="text-sm text-green-700">{{ message }}</p>
        <p v-if="errorMsg" class="text-sm text-red-600">{{ errorMsg }}</p>
        <div v-if="auth.can('settings.general.edit')" class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Guardando…' : 'Guardar configuración' }}
          </button>
        </div>
      </form>
    </div>
  </DefaultLayout>
</template>
