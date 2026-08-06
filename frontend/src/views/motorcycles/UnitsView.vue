<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import ImportModal from '@/components/ui/ImportModal.vue'
import { modelService, unitService } from '@/services/motorcycles'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import type { ModelItem, UnitItem, UnitStatus } from '@/types/motorcycles'
import { SYSTEM_STATUSES, UNIT_STATUSES } from '@/types/motorcycles'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'internalCode', label: 'Código', sortable: true },
  { key: 'vin', label: 'VIN', sortable: true },
  { key: 'modelName', label: 'Modelo' },
  { key: 'color', label: 'Color' },
  { key: 'status', label: 'Estado', sortable: true },
]

const STATUS_COLORS: Record<UnitStatus, string> = {
  DISPONIBLE: 'bg-green-100 text-green-800',
  RESERVADA: 'bg-yellow-100 text-yellow-800',
  VENDIDA: 'bg-blue-100 text-blue-800',
  EN_TALLER: 'bg-orange-100 text-orange-800',
  GARANTIA: 'bg-purple-100 text-purple-800',
  BAJA: 'bg-gray-200 text-gray-600',
}

const rows = ref<UnitItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const models = ref<ModelItem[]>([])
const statusFilter = ref('')
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'internalCode', direction: 'asc' as const })

const modalOpen = ref(false)
const editing = ref<UnitItem | null>(null)
const saving = ref(false)
const formError = ref('')
const emptyForm = {
  internalCode: '',
  vin: '',
  modelId: 0,
  color: '',
  engineNumber: null as string | null,
  chassisNumber: null as string | null,
  series: null as string | null,
  entryDate: null as string | null,
  purchasePrice: null as number | null,
  salePrice: null as number | null,
  notes: null as string | null,
  duaNumber: null as string | null,
  duaItem: null as string | null,
}
const form = reactive({ ...emptyForm })

/** Modelo seleccionado y sus colores disponibles (para el desplegable de color). */
const selectedModel = computed(() => models.value.find((m) => m.id === form.modelId) ?? null)
const modelColors = computed<string[]>(() =>
  (selectedModel.value?.colors ?? '')
    .split(',')
    .map((c) => c.trim())
    .filter((c) => c !== ''),
)

/** Al elegir modelo: prellena el precio de venta con el referencial del modelo. */
function onModelChange(): void {
  const m = selectedModel.value
  if (m && (form.salePrice === null || form.salePrice === 0) && m.referencePrice !== null) {
    form.salePrice = Number(m.referencePrice)
  }
  if (modelColors.value.length && !modelColors.value.includes(form.color)) {
    form.color = ''
  }
}

const confirmTarget = ref<UnitItem | null>(null)
const statusTarget = ref<UnitItem | null>(null)
const newStatus = ref<UnitStatus>('DISPONIBLE')

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await unitService.list({ ...query, status: statusFilter.value })
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
  Object.assign(form, emptyForm, { modelId: models.value[0]?.id ?? 0 })
  formError.value = ''
  modalOpen.value = true
}

function openEdit(unit: UnitItem): void {
  editing.value = unit
  Object.assign(form, {
    internalCode: unit.internalCode,
    vin: unit.vin,
    modelId: unit.modelId,
    color: unit.color,
    engineNumber: unit.engineNumber,
    chassisNumber: unit.chassisNumber,
    series: unit.series,
    entryDate: unit.entryDate,
    purchasePrice: unit.purchasePrice !== null ? Number(unit.purchasePrice) : null,
    salePrice: unit.salePrice !== null ? Number(unit.salePrice) : null,
    notes: unit.notes,
    duaNumber: unit.duaNumber,
    duaItem: unit.duaItem,
  })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await unitService.update(editing.value.id, { ...form })
    } else {
      await unitService.create({ ...form })
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar.'
  } finally {
    saving.value = false
  }
}

async function applyStatus(): Promise<void> {
  if (!statusTarget.value) return
  try {
    await unitService.changeStatus(statusTarget.value.id, newStatus.value)
    statusTarget.value = null
    await load()
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? 'No se pudo cambiar el estado.'
    statusTarget.value = null
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await unitService.remove(confirmTarget.value.id)
  } finally {
    confirmTarget.value = null
    await load()
  }
}

const importOpen = ref(false)

onMounted(async () => {
  await load()
  const modelsResult = await modelService.list({ page: 1, perPage: 100, search: '', sort: 'model', direction: 'asc' })
  models.value = modelsResult.data.filter((m) => m.isActive)
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <button
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="statusFilter === '' ? 'bg-primary-600 text-white' : 'border border-gray-300 bg-white text-gray-700'"
        @click="statusFilter = ''; load()"
      >
        Todas
      </button>
      <button
        v-for="s in UNIT_STATUSES"
        :key="s"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="statusFilter === s ? 'bg-primary-600 text-white' : 'border border-gray-300 bg-white text-gray-700'"
        @click="statusFilter = s; load()"
      >
        {{ s.replace('_', ' ') }}
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por VIN, código, motor o modelo…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('motorcycles.units.create')" class="btn-secondary" @click="importOpen = true">
          Importar Excel
        </button>
        <button v-if="auth.can('motorcycles.units.create')" class="btn-primary" @click="openCreate">
          Nueva unidad
        </button>
      </template>
      <template #cell-vin="{ row }">
        <span class="font-mono text-xs">{{ row.vin }}</span>
      </template>
      <template #cell-status="{ row }">
        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="STATUS_COLORS[(row as unknown as UnitItem).status]">
          {{ String(row.status).replace('_', ' ') }}
        </span>
      </template>
      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button
            v-if="auth.can('motorcycles.units.edit') && row.status !== 'VENDIDA'"
            class="btn-secondary"
            @click="openEdit(row as unknown as UnitItem)"
          >
            Editar
          </button>
          <button
            v-if="auth.can('motorcycles.units.edit') && row.status !== 'VENDIDA'"
            class="btn-secondary"
            @click="statusTarget = row as unknown as UnitItem; newStatus = 'DISPONIBLE'"
          >
            Estado
          </button>
          <button
            v-if="auth.can('motorcycles.units.delete') && row.status !== 'VENDIDA'"
            class="btn-secondary !text-red-600"
            @click="confirmTarget = row as unknown as UnitItem"
          >
            Baja
          </button>
        </div>
      </template>
    </DataTable>

    <BaseModal :open="modalOpen" :title="editing ? `Editar unidad: ${editing.internalCode}` : 'Nueva unidad'" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Código Interno" required>
            <input v-model="form.internalCode" class="form-input uppercase" required maxlength="20" />
          </FormField>
          <FormField label="VIN (17 caracteres)" required>
            <input v-model="form.vin" class="form-input font-mono uppercase" required maxlength="17" minlength="17" :disabled="editing !== null" />
          </FormField>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Modelo" required>
            <select v-model.number="form.modelId" class="form-input" required @change="onModelChange">
              <option v-for="m in models" :key="m.id" :value="m.id">{{ m.fullName }}</option>
            </select>
          </FormField>
          <FormField label="Color" required>
            <select v-if="modelColors.length" v-model="form.color" class="form-input" required>
              <option value="">— elige color —</option>
              <option v-for="c in modelColors" :key="c" :value="c">{{ c }}</option>
            </select>
            <input v-else v-model="form.color" class="form-input" required maxlength="50" placeholder="Color" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Número de Motor">
            <input v-model="form.engineNumber" class="form-input uppercase" maxlength="30" />
          </FormField>
          <FormField label="Número de Chasis">
            <input v-model="form.chassisNumber" class="form-input" maxlength="30" />
          </FormField>
          <FormField label="Serie">
            <input v-model="form.series" class="form-input" maxlength="30" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Fecha de Ingreso">
            <input v-model="form.entryDate" type="date" class="form-input" />
          </FormField>
          <FormField label="Precio Compra (S/)">
            <input v-model.number="form.purchasePrice" type="number" step="0.01" class="form-input" min="0" />
          </FormField>
          <FormField label="Precio Venta (S/)">
            <input v-model.number="form.salePrice" type="number" step="0.01" class="form-input" min="0" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="DUA (importación)" class="col-span-2">
            <input v-model="form.duaNumber" class="form-input" maxlength="40" placeholder="118-2026-10-177946-01-4-00" />
          </FormField>
          <FormField label="Ítem DUA">
            <input v-model="form.duaItem" class="form-input" maxlength="10" placeholder="34" />
          </FormField>
        </div>
        <FormField label="Observaciones">
          <input v-model="form.notes" class="form-input" />
        </FormField>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :open="statusTarget !== null" :title="`Cambiar estado: ${statusTarget?.internalCode}`" @close="statusTarget = null">
      <FormField label="Nuevo estado">
        <select v-model="newStatus" class="form-input">
          <option v-for="s in UNIT_STATUSES.filter((x) => !SYSTEM_STATUSES.includes(x))" :key="s" :value="s">
            {{ s.replace('_', ' ') }}
          </option>
        </select>
      </FormField>
      <p class="mt-2 text-xs text-gray-500">
        Los estados VENDIDA, EN TALLER y GARANTÍA los asigna el sistema automáticamente.
      </p>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" class="btn-secondary" @click="statusTarget = null">Cancelar</button>
        <button type="button" class="btn-primary" @click="applyStatus">Aplicar</button>
      </div>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Dar de baja unidad"
      :message="`La unidad «${confirmTarget?.internalCode}» (VIN ${confirmTarget?.vin}) pasará a estado BAJA y se ocultará. Su expediente se conserva. ¿Continuar?`"
      confirm-label="Dar de baja"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />

    <ImportModal
      :open="importOpen"
      title="Importar motos desde Excel/CSV"
      code-header="VIN"
      :download="unitService.downloadImportTemplate"
      :run="unitService.importFile"
      @close="importOpen = false"
      @imported="load"
    >
      <template #help>
        <p class="text-xs">
          Columnas: Código Interno · VIN · Modelo · Color · Nº de Motor · Nº de Chasis · Serie · Año ·
          Fecha de Ingreso · Proveedor (RUC) · Precio de Compra · Precio de Venta · Ubicación. La clave es el
          <strong>VIN</strong>. El <strong>Modelo</strong> debe escribirse igual que en Modelos (p. ej.
          «Yamaha YBR125 2024»). Cada fila es una moto física; las motos vendidas no se modifican.
        </p>
      </template>
    </ImportModal>
  </DefaultLayout>
</template>
