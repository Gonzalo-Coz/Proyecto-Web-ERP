<script setup lang="ts">
import { ref } from 'vue'
import { catalogService } from '@/services/catalogs'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { CatalogItem, CatalogType } from '@/types/catalogs'

/**
 * Selector de catálogo (marca, categoría, etc.) con creación al vuelo.
 * Reutilizable en cualquier formulario: evita salir a Configuración → Catálogos
 * solo para agregar una marca. La fuente de datos sigue siendo el mismo catálogo
 * (no duplica lógica); el alta usa `catalogService`.
 */
const props = withDefaults(
  defineProps<{
    modelValue: number | null
    items: CatalogItem[]
    type: CatalogType
    allowNull?: boolean
    addLabel?: string
  }>(),
  { allowNull: true, addLabel: 'Nuevo' },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void
  (e: 'created', item: CatalogItem): void
}>()

const auth = useAuthStore()
const toast = useToast()
const canCreate = auth.can('settings.catalogs.create')

const adding = ref(false)
const newName = ref('')
const saving = ref(false)

function onSelect(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  emit('update:modelValue', value === '' ? null : Number(value))
}

async function confirmAdd(): Promise<void> {
  const name = newName.value.trim()
  if (name === '') return
  saving.value = true
  try {
    const item = await catalogService.create(props.type, { name, code: null, isActive: true })
    emit('created', item) // el padre lo agrega a su lista
    emit('update:modelValue', item.id) // queda seleccionado
    newName.value = ''
    adding.value = false
    toast.success('Agregado correctamente.')
  } catch (error: any) {
    toast.error(error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo agregar.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <div class="flex gap-2">
      <select :value="modelValue ?? ''" class="form-input" @change="onSelect">
        <option v-if="allowNull" value="">—</option>
        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option>
      </select>
      <button
        v-if="canCreate"
        type="button"
        class="btn-secondary shrink-0"
        :title="`Agregar ${addLabel.toLowerCase()}`"
        @click="adding = !adding"
      >
        +
      </button>
    </div>

    <div v-if="adding" class="mt-2 flex gap-2">
      <input
        v-model="newName"
        class="form-input"
        :placeholder="`${addLabel}…`"
        maxlength="100"
        @keyup.enter.prevent="confirmAdd"
      />
      <button type="button" class="btn-primary shrink-0" :disabled="saving || !newName.trim()" @click="confirmAdd">
        {{ saving ? '…' : 'Agregar' }}
      </button>
      <button type="button" class="btn-secondary shrink-0" @click="adding = false">Cancelar</button>
    </div>
  </div>
</template>
