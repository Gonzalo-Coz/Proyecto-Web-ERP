<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { modelService } from '@/services/motorcycles'
import { catalogService } from '@/services/catalogs'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import type { CatalogItem } from '@/types/catalogs'
import type { ModelItem } from '@/types/motorcycles'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'brandName', label: 'Marca' },
  { key: 'model', label: 'Modelo', sortable: true },
  { key: 'version', label: 'Versión' },
  { key: 'modelYear', label: 'Año', sortable: true },
  { key: 'engineCapacity', label: 'Cilindraje' },
  { key: 'isActive', label: 'Estado' },
]

const rows = ref<ModelItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const brands = ref<CatalogItem[]>([])
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'model', direction: 'asc' as const })

const modalOpen = ref(false)
const editing = ref<ModelItem | null>(null)
const saving = ref(false)
const formError = ref('')
const emptyForm = {
  brandId: 0,
  model: '',
  version: null as string | null,
  modelYear: new Date().getFullYear(),
  engineCapacity: null as string | null,
  engineType: null as string | null,
  power: null as string | null,
  fuelType: null as string | null,
  transmission: null as string | null,
  tankCapacity: null as string | null,
  weight: null as string | null,
  colors: null as string | null,
  warrantyMonths: null as number | null,
  referencePrice: null as number | null,
  isActive: true,
}
const form = reactive({ ...emptyForm })
const confirmTarget = ref<ModelItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await modelService.list(query)
    rows.value = result.data
    meta.value = result.meta
  } finally {
    loading.value = false
  }
}

function onTableChange(p: { page: number; search: string; sort: string; direction: 'asc' | 'desc' }): void {
  Object.assign(query, { page: p.page, search: p.search })
  if (p.sort) Object.assign(query, { sort: p.sort, direction: p.direction })
  load()
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyForm, { brandId: brands.value[0]?.id ?? 0 })
  formError.value = ''
  modalOpen.value = true
}

function openEdit(item: ModelItem): void {
  editing.value = item
  const { id, brandName, fullName, referencePrice, ...rest } = item
  Object.assign(form, rest, { referencePrice: referencePrice !== null ? Number(referencePrice) : null })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await modelService.update(editing.value.id, { ...form })
    } else {
      await modelService.create({ ...form })
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await modelService.remove(confirmTarget.value.id)
  } finally {
    confirmTarget.value = null
    await load()
  }
}

onMounted(async () => {
  await load()
  brands.value = (await catalogService.list('brands')).filter((b) => b.isActive)
})
</script>

<template>
  <DefaultLayout>
    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por marca, modelo o versión…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('motorcycles.models.create')" class="btn-primary" @click="openCreate">
          Nuevo modelo
        </button>
      </template>
      <template #cell-isActive="{ row }">
        <StatusBadge :active="Boolean(row.isActive)" />
      </template>
      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button v-if="auth.can('motorcycles.models.edit')" class="btn-secondary" @click="openEdit(row as unknown as ModelItem)">Editar</button>
          <button v-if="auth.can('motorcycles.models.delete')" class="btn-secondary !text-red-600" @click="confirmTarget = row as unknown as ModelItem">Eliminar</button>
        </div>
      </template>
    </DataTable>

    <BaseModal :open="modalOpen" :title="editing ? `Editar modelo: ${editing.fullName}` : 'Nuevo modelo'" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Marca" required>
            <select v-model.number="form.brandId" class="form-input" required>
              <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </FormField>
          <FormField label="Año Modelo" required>
            <input v-model.number="form.modelYear" type="number" class="form-input" required min="1990" max="2100" />
          </FormField>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Modelo" required>
            <input v-model="form.model" class="form-input" required maxlength="100" />
          </FormField>
          <FormField label="Versión">
            <input v-model="form.version" class="form-input" maxlength="100" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Cilindraje">
            <input v-model="form.engineCapacity" class="form-input" maxlength="30" placeholder="249 cc" />
          </FormField>
          <FormField label="Potencia">
            <input v-model="form.power" class="form-input" maxlength="50" />
          </FormField>
          <FormField label="Combustible">
            <input v-model="form.fuelType" class="form-input" maxlength="30" placeholder="Gasolina" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Tipo de Motor">
            <input v-model="form.engineType" class="form-input" maxlength="100" />
          </FormField>
          <FormField label="Transmisión">
            <input v-model="form.transmission" class="form-input" maxlength="50" />
          </FormField>
          <FormField label="Tanque">
            <input v-model="form.tankCapacity" class="form-input" maxlength="30" placeholder="14 L" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Peso">
            <input v-model="form.weight" class="form-input" maxlength="30" placeholder="153 kg" />
          </FormField>
          <FormField label="Garantía (meses)">
            <input v-model.number="form.warrantyMonths" type="number" class="form-input" min="0" />
          </FormField>
          <FormField label="Precio Referencial (S/)">
            <input v-model.number="form.referencePrice" type="number" step="0.01" class="form-input" min="0" />
          </FormField>
        </div>
        <FormField label="Colores disponibles (separados por coma)">
          <input v-model="form.colors" class="form-input" maxlength="200" placeholder="Azul Racing, Negro, Gris" />
        </FormField>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Modelo activo
        </label>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </form>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Eliminar modelo"
      :message="`El modelo «${confirmTarget?.fullName}» se desactivará (eliminación lógica). Las unidades existentes no se ven afectadas. ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
