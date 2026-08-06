<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import { useToast } from '@/composables/useToast'
import type { ImportResult } from '@/types/import'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    codeHeader?: string
    download: () => Promise<void>
    run: (file: File, dryRun: boolean) => Promise<ImportResult>
  }>(),
  { codeHeader: 'Código' },
)
const emit = defineEmits<{ (e: 'close'): void; (e: 'imported'): void }>()

const toast = useToast()
const file = ref<File | null>(null)
const result = ref<ImportResult | null>(null)
const busy = ref(false)
const error = ref('')

watch(
  () => props.open,
  (v) => {
    if (v) {
      file.value = null
      result.value = null
      error.value = ''
    }
  },
)

function onFile(e: Event): void {
  file.value = (e.target as HTMLInputElement).files?.[0] ?? null
  result.value = null
  error.value = ''
}

async function downloadTemplate(): Promise<void> {
  try {
    await props.download()
  } catch {
    toast.error('No se pudo descargar la plantilla.')
  }
}

async function execute(dryRun: boolean): Promise<void> {
  if (!file.value) return
  busy.value = true
  error.value = ''
  try {
    result.value = await props.run(file.value, dryRun)
    if (!dryRun) {
      const s = result.value.summary
      toast.success(`Importación aplicada: ${s.create} creados, ${s.update} actualizados.`)
      emit('imported')
    }
  } catch (e: any) {
    error.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo procesar el archivo.'
  } finally {
    busy.value = false
  }
}

const applicable = computed(() =>
  result.value ? result.value.summary.create + result.value.summary.update : 0,
)
const statusText: Record<string, string> = { create: 'Crear', update: 'Actualizar', error: 'Error' }
const statusClass: Record<string, string> = {
  create: 'bg-green-100 text-green-800',
  update: 'bg-blue-100 text-blue-800',
  error: 'bg-red-100 text-red-800',
}
</script>

<template>
  <BaseModal :open="open" :title="title" size="xl" @close="emit('close')">
    <div class="space-y-4">
      <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
        <p class="mb-2">
          <strong>Cómo funciona:</strong> descarga la plantilla, complétala en Excel y súbela. Si un
          <strong>código</strong> ya existe, se <strong>actualiza</strong>; si no, se <strong>crea</strong>.
          Verás una vista previa antes de guardar.
        </p>
        <slot name="help" />
        <button class="btn-secondary mt-2" @click="downloadTemplate">Descargar plantilla</button>
      </div>

      <FormField label="Archivo (.csv o Excel guardado como CSV)">
        <input type="file" accept=".csv,text/csv,.xlsx" class="form-input" @change="onFile" />
      </FormField>

      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary" :disabled="!file || busy" @click="execute(true)">
          {{ busy ? 'Procesando…' : 'Previsualizar' }}
        </button>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div v-if="result" class="space-y-3">
        <div class="flex flex-wrap gap-2 text-sm">
          <span class="rounded-full bg-gray-100 px-3 py-1">Total: <strong>{{ result.summary.total }}</strong></span>
          <span class="rounded-full bg-green-100 px-3 py-1 text-green-800">Crear: <strong>{{ result.summary.create }}</strong></span>
          <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-800">Actualizar: <strong>{{ result.summary.update }}</strong></span>
          <span class="rounded-full bg-red-100 px-3 py-1 text-red-800">Errores: <strong>{{ result.summary.error }}</strong></span>
        </div>

        <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200">
          <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-gray-50 text-xs uppercase text-gray-500">
              <tr>
                <th class="px-3 py-2">Fila</th>
                <th class="px-3 py-2">{{ codeHeader }}</th>
                <th class="px-3 py-2">Detalle</th>
                <th class="px-3 py-2">Acción</th>
                <th class="px-3 py-2">Mensaje</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in result.rows" :key="r.line" class="border-t border-gray-100">
                <td class="px-3 py-2 text-gray-500">{{ r.line }}</td>
                <td class="px-3 py-2 font-medium">{{ r.code || '—' }}</td>
                <td class="px-3 py-2">{{ r.label || '—' }}</td>
                <td class="px-3 py-2">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[r.status]">
                    {{ statusText[r.status] }}
                  </span>
                </td>
                <td class="px-3 py-2 text-xs text-gray-600">{{ r.message }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="result.committed" class="text-sm font-medium text-green-700">
          ✓ Importación aplicada. Puedes cerrar esta ventana.
        </p>
        <p v-else-if="result.summary.error > 0" class="text-xs text-amber-700">
          Hay filas con error: se omitirán al confirmar. Corrígelas en el Excel si quieres incluirlas.
        </p>
      </div>

      <div class="flex justify-end gap-3 border-t border-gray-200 pt-3">
        <button class="btn-secondary" @click="emit('close')">Cerrar</button>
        <button
          v-if="result && !result.committed && applicable > 0"
          class="btn-primary"
          :disabled="busy"
          @click="execute(false)"
        >
          {{ busy ? 'Aplicando…' : `Confirmar (${applicable})` }}
        </button>
      </div>
    </div>
  </BaseModal>
</template>
