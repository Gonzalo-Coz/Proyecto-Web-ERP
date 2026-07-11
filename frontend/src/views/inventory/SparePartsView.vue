<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import { sparePartService } from '@/services/inventory'
import { modelService } from '@/services/motorcycles'
import { catalogService } from '@/services/catalogs'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import type { CatalogItem } from '@/types/catalogs'
import type { ModelItem } from '@/types/motorcycles'
import type { KardexEntry, SparePartItem } from '@/types/inventory'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'internalCode', label: 'Código', sortable: true },
  { key: 'partCode', label: 'Cód. Repuesto', sortable: true },
  { key: 'description', label: 'Descripción', sortable: true },
  { key: 'categoryName', label: 'Categoría' },
  { key: 'stock', label: 'Stock', sortable: true },
]

const rows = ref<SparePartItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const brands = ref<CatalogItem[]>([])
const categories = ref<CatalogItem[]>([])
const models = ref<ModelItem[]>([])
const stockFilter = ref('')
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'description', direction: 'asc' as const })

const modalOpen = ref(false)
const editing = ref<SparePartItem | null>(null)
const saving = ref(false)
const formError = ref('')
const emptyForm = {
  internalCode: '',
  partCode: '',
  barcode: null as string | null,
  description: '',
  brandId: null as number | null,
  categoryId: null as number | null,
  unitOfMeasure: 'UNIDAD',
  compatibleModelIds: [] as number[],
  minStock: 0,
  maxStock: null as number | null,
  purchasePrice: null as number | null,
  salePrice: null as number | null,
  location: null as string | null,
  isActive: true,
}
const form = reactive({ ...emptyForm })
const confirmTarget = ref<SparePartItem | null>(null)

// Kardex y ajuste
const kardexTarget = ref<SparePartItem | null>(null)
const kardexEntries = ref<KardexEntry[]>([])
const kardexMeta = ref<PageMeta | null>(null)
const adjustTarget = ref<SparePartItem | null>(null)
const adjustQty = ref(0)
const adjustReason = ref('')
const adjustError = ref('')

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await sparePartService.list({ ...query, stockFilter: stockFilter.value })
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
  Object.assign(form, emptyForm, { compatibleModelIds: [] })
  formError.value = ''
  modalOpen.value = true
}

function openEdit(part: SparePartItem): void {
  editing.value = part
  Object.assign(form, {
    internalCode: part.internalCode,
    partCode: part.partCode,
    barcode: part.barcode,
    description: part.description,
    brandId: part.brandId,
    categoryId: part.categoryId,
    unitOfMeasure: part.unitOfMeasure,
    compatibleModelIds: [...part.compatibleModelIds],
    minStock: part.minStock,
    maxStock: part.maxStock,
    purchasePrice: part.purchasePrice !== null ? Number(part.purchasePrice) : null,
    salePrice: part.salePrice !== null ? Number(part.salePrice) : null,
    location: part.location,
    isActive: part.isActive,
  })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await sparePartService.update(editing.value.id, { ...form })
    } else {
      await sparePartService.create({ ...form })
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar.'
  } finally {
    saving.value = false
  }
}

async function openKardex(part: SparePartItem, page = 1): Promise<void> {
  kardexTarget.value = part
  const result = await sparePartService.kardex(part.id, page)
  kardexEntries.value = result.data
  kardexMeta.value = result.meta
}

function openAdjust(part: SparePartItem): void {
  adjustTarget.value = part
  adjustQty.value = 0
  adjustReason.value = ''
  adjustError.value = ''
}

async function applyAdjust(): Promise<void> {
  if (!adjustTarget.value) return
  adjustError.value = ''
  try {
    await sparePartService.adjust(adjustTarget.value.id, adjustQty.value, adjustReason.value)
    adjustTarget.value = null
    await load()
  } catch (error: any) {
    adjustError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo aplicar el ajuste.'
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await sparePartService.remove(confirmTarget.value.id)
  } finally {
    confirmTarget.value = null
    await load()
  }
}

onMounted(async () => {
  await load()
  brands.value = (await catalogService.list('brands')).filter((b) => b.isActive)
  categories.value = (await catalogService.list('categories')).filter((c) => c.isActive)
  const modelsResult = await modelService.list({ page: 1, perPage: 100, search: '', sort: 'model', direction: 'asc' })
  models.value = modelsResult.data.filter((m) => m.isActive)
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-4 flex gap-2">
      <button
        v-for="f in [{ k: '', l: 'Todos' }, { k: 'low', l: 'Stock bajo' }, { k: 'out', l: 'Sin stock' }]"
        :key="f.k"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="stockFilter === f.k ? 'bg-primary-600 text-white' : 'border border-gray-300 bg-white text-gray-700'"
        @click="stockFilter = f.k; load()"
      >
        {{ f.l }}
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por código, descripción o código de barras…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('inventory.spare_parts.create')" class="btn-primary" @click="openCreate">
          Nuevo repuesto
        </button>
      </template>
      <template #cell-stock="{ row }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="(row as unknown as SparePartItem).isOutOfStock ? 'bg-red-100 text-red-800' : (row as unknown as SparePartItem).isLowStock ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'"
        >
          {{ row.stock }} {{ row.unitOfMeasure }}
        </span>
      </template>
      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button v-if="auth.can('inventory.kardex.view')" class="btn-secondary" @click="openKardex(row as unknown as SparePartItem)">Kardex</button>
          <button v-if="auth.can('inventory.adjustments.create')" class="btn-secondary" @click="openAdjust(row as unknown as SparePartItem)">Ajustar</button>
          <button v-if="auth.can('inventory.spare_parts.edit')" class="btn-secondary" @click="openEdit(row as unknown as SparePartItem)">Editar</button>
          <button v-if="auth.can('inventory.spare_parts.delete')" class="btn-secondary !text-red-600" @click="confirmTarget = row as unknown as SparePartItem">Eliminar</button>
        </div>
      </template>
    </DataTable>

    <!-- Formulario de repuesto -->
    <BaseModal :open="modalOpen" :title="editing ? `Editar repuesto: ${editing.internalCode}` : 'Nuevo repuesto'" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Código Interno" required>
            <input v-model="form.internalCode" class="form-input uppercase" required maxlength="20" />
          </FormField>
          <FormField label="Código de Repuesto" required>
            <input v-model="form.partCode" class="form-input uppercase" required maxlength="40" placeholder="5SL-E3440-00" />
          </FormField>
          <FormField label="Código de Barras">
            <input v-model="form.barcode" class="form-input" maxlength="50" />
          </FormField>
        </div>
        <FormField label="Descripción" required>
          <input v-model="form.description" class="form-input" required maxlength="200" />
        </FormField>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Marca">
            <select v-model.number="form.brandId" class="form-input">
              <option :value="null">—</option>
              <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </FormField>
          <FormField label="Categoría">
            <select v-model.number="form.categoryId" class="form-input">
              <option :value="null">—</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </FormField>
          <FormField label="Unidad de Medida">
            <input v-model="form.unitOfMeasure" class="form-input uppercase" maxlength="20" />
          </FormField>
        </div>
        <FormField label="Modelos compatibles">
          <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-3">
            <label v-for="m in models" :key="m.id" class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.compatibleModelIds" type="checkbox" :value="m.id" />
              {{ m.fullName }}
            </label>
            <p v-if="models.length === 0" class="text-xs text-gray-400">No hay modelos registrados.</p>
          </div>
        </FormField>
        <div class="grid grid-cols-4 gap-4">
          <FormField label="Stock Mínimo">
            <input v-model.number="form.minStock" type="number" class="form-input" min="0" />
          </FormField>
          <FormField label="Stock Máximo">
            <input v-model.number="form.maxStock" type="number" class="form-input" min="0" />
          </FormField>
          <FormField label="Precio Compra (S/)">
            <input v-model.number="form.purchasePrice" type="number" step="0.01" class="form-input" min="0" />
          </FormField>
          <FormField label="Precio Venta (S/)">
            <input v-model.number="form.salePrice" type="number" step="0.01" class="form-input" min="0" />
          </FormField>
        </div>
        <FormField label="Ubicación">
          <input v-model="form.location" class="form-input" maxlength="100" placeholder="Estante A-3" />
        </FormField>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Repuesto activo
        </label>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </form>
      <p v-if="!editing" class="mt-3 text-xs text-gray-500">
        El stock inicial se registra mediante un Ajuste o una Compra, nunca directamente (todo pasa por el Kardex).
      </p>
    </BaseModal>

    <!-- Kardex -->
    <BaseModal :open="kardexTarget !== null" :title="`Kardex: ${kardexTarget?.description}`" @close="kardexTarget = null">
      <table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-500">
          <tr>
            <th class="py-2">Fecha</th>
            <th class="py-2">Tipo</th>
            <th class="py-2 text-right">Cant.</th>
            <th class="py-2 text-right">Saldo</th>
            <th class="py-2">Usuario</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="kardexEntries.length === 0">
            <td colspan="5" class="py-6 text-center text-gray-400">Sin movimientos.</td>
          </tr>
          <tr v-for="e in kardexEntries" :key="e.id" class="border-t border-gray-100">
            <td class="py-2 text-xs">{{ new Date(e.createdAt).toLocaleString() }}</td>
            <td class="py-2">{{ e.movementType }}</td>
            <td class="py-2 text-right" :class="e.quantity > 0 ? 'text-green-700' : 'text-red-700'">
              {{ e.quantity > 0 ? '+' : '' }}{{ e.quantity }}
            </td>
            <td class="py-2 text-right font-medium">{{ e.balanceAfter }}</td>
            <td class="py-2 text-xs text-gray-500">{{ e.username ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="kardexMeta && kardexMeta.totalPages > 1" class="mt-4 flex justify-end gap-2">
        <button class="btn-secondary" :disabled="kardexMeta.page <= 1" @click="openKardex(kardexTarget!, kardexMeta.page - 1)">Anterior</button>
        <button class="btn-secondary" :disabled="kardexMeta.page >= kardexMeta.totalPages" @click="openKardex(kardexTarget!, kardexMeta.page + 1)">Siguiente</button>
      </div>
    </BaseModal>

    <!-- Ajuste manual -->
    <BaseModal :open="adjustTarget !== null" :title="`Ajustar stock: ${adjustTarget?.description}`" @close="adjustTarget = null">
      <p class="mb-4 text-sm text-gray-600">Stock actual: <strong>{{ adjustTarget?.stock }}</strong></p>
      <div class="space-y-4">
        <FormField label="Cantidad (+entra / −sale)" required>
          <input v-model.number="adjustQty" type="number" class="form-input" required />
        </FormField>
        <FormField label="Motivo del ajuste" required>
          <input v-model="adjustReason" class="form-input" required minlength="5" maxlength="200" placeholder="Inventario inicial, merma, conteo físico…" />
        </FormField>
        <p v-if="adjustError" class="text-sm text-red-600">{{ adjustError }}</p>
        <div class="flex justify-end gap-3">
          <button type="button" class="btn-secondary" @click="adjustTarget = null">Cancelar</button>
          <button type="button" class="btn-primary" @click="applyAdjust">Aplicar ajuste</button>
        </div>
      </div>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Eliminar repuesto"
      :message="`«${confirmTarget?.description}» se desactivará (eliminación lógica). Su Kardex se conserva. ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
