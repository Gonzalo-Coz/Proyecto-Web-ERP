<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import CatalogSelect from '@/components/ui/CatalogSelect.vue'
import { UNITS_OF_MEASURE } from '@/constants/units'
import api from '@/services/api'
import { sparePartService, type ImportResult } from '@/services/inventory'
import { modelService } from '@/services/motorcycles'
import { catalogService } from '@/services/catalogs'
import { supplierService } from '@/services/masters'
import { purchaseService } from '@/services/purchases'
import type { SupplierItem } from '@/types/masters'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { CatalogItem } from '@/types/catalogs'
import type { ModelItem } from '@/types/motorcycles'
import type { KardexEntry, SparePartItem } from '@/types/inventory'

const auth = useAuthStore()
const toast = useToast()

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
  description: '',
  brandId: null as number | null,
  categoryId: null as number | null,
  unitOfMeasure: 'UNIDAD',
  compatibleModelIds: [] as number[],
  minStock: 0,
  purchasePrice: null as number | null,
  salePrice: null as number | null,
  location: null as string | null,
  isActive: true,
  priceChangeReason: null as string | null,
}
const form = reactive({ ...emptyForm })
/** Muestra el motivo solo al editar y cuando el precio de venta cambió (Adición A3). */
const salePriceChanged = computed(
  () => editing.value !== null && Number(form.salePrice ?? 0) !== Number(editing.value.salePrice ?? 0),
)

/** Moneda de los precios ingresados; US$ se convierte a soles con el T.C. del día. */
const priceCurrency = ref<'PEN' | 'USD'>('PEN')
const dayRate = ref<number | null>(null)
const toSoles = (v: number | null): number | null =>
  v !== null && priceCurrency.value === 'USD' && dayRate.value ? Math.round(v * dayRate.value * 100) / 100 : v

/** Registrar también como compra (ingresa stock y costo). */
const suppliers = ref<SupplierItem[]>([])
const regPurchase = ref(true)
const regSupplierId = ref<number | null>(null)
const regQty = ref(1)
const confirmTarget = ref<SparePartItem | null>(null)

// Kardex y ajuste
const kardexTarget = ref<SparePartItem | null>(null)
const kardexEntries = ref<KardexEntry[]>([])
const kardexMeta = ref<PageMeta | null>(null)
const adjustTarget = ref<SparePartItem | null>(null)
const adjustQty = ref(0)
const adjustReason = ref('')
const adjustError = ref('')

// Importación masiva (CSV/Excel)
const importOpen = ref(false)
const importFile = ref<File | null>(null)
const importResult = ref<ImportResult | null>(null)
const importing = ref(false)
const importError = ref('')

function openImport(): void {
  importFile.value = null
  importResult.value = null
  importError.value = ''
  importOpen.value = true
}

async function downloadTemplate(): Promise<void> {
  try {
    await sparePartService.downloadImportTemplate()
  } catch {
    toast.error('No se pudo descargar la plantilla.')
  }
}

function onImportFile(e: Event): void {
  const target = e.target as HTMLInputElement
  importFile.value = target.files?.[0] ?? null
  importResult.value = null
  importError.value = ''
}

async function runImport(dryRun: boolean): Promise<void> {
  if (!importFile.value) return
  importing.value = true
  importError.value = ''
  try {
    importResult.value = await sparePartService.importFile(importFile.value, dryRun)
    if (!dryRun) {
      const s = importResult.value.summary
      toast.success(`Importación aplicada: ${s.create} creados, ${s.update} actualizados.`)
      await load()
    }
  } catch (e: any) {
    importError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo procesar el archivo.'
  } finally {
    importing.value = false
  }
}

const importHasErrors = computed(() => (importResult.value?.summary.error ?? 0) > 0)
const importStatusText: Record<string, string> = { create: 'Crear', update: 'Actualizar', error: 'Error' }
const importStatusClass: Record<string, string> = {
  create: 'bg-green-100 text-green-800',
  update: 'bg-blue-100 text-blue-800',
  error: 'bg-red-100 text-red-800',
}

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
    description: part.description,
    brandId: part.brandId,
    categoryId: part.categoryId,
    unitOfMeasure: part.unitOfMeasure,
    compatibleModelIds: [...part.compatibleModelIds],
    minStock: part.minStock,
    purchasePrice: part.purchasePrice !== null ? Number(part.purchasePrice) : null,
    salePrice: part.salePrice !== null ? Number(part.salePrice) : null,
    location: part.location,
    isActive: part.isActive,
    priceChangeReason: null,
  })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const cost = toSoles(form.purchasePrice)
    const payload = { ...form, purchasePrice: cost, salePrice: toSoles(form.salePrice) }
    if (editing.value) {
      await sparePartService.update(editing.value.id, payload)
    } else {
      const created = await sparePartService.create(payload)
      // Registrar automáticamente como compra (ingresa stock + costo).
      if (regPurchase.value && regSupplierId.value) {
        await purchaseService.create({
          supplierId: regSupplierId.value,
          purchaseDate: new Date().toISOString().slice(0, 10),
          documentType: 'OTRO',
          items: [{ itemType: 'SPARE_PART', sparePartId: created.id, motorcycleUnitId: null, quantity: regQty.value, unitPrice: cost ?? 0, discount: 0 }],
          series: null,
          documentNumber: null,
          paymentMethodId: null,
          notes: 'Ingreso al crear repuesto',
        })
      }
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
  try {
    dayRate.value = (await api.get('/exchange-rate')).data.sell
  } catch {
    dayRate.value = null
  }
  try {
    suppliers.value = (await supplierService.list({ page: 1, perPage: 100, search: '', sort: 'businessName', direction: 'asc' })).data.filter((s) => s.isActive)
    regSupplierId.value = suppliers.value[0]?.id ?? null
  } catch {
    suppliers.value = []
  }
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
        <button v-if="auth.can('inventory.spare_parts.create')" class="btn-secondary" @click="openImport">
          Importar Excel
        </button>
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
    <BaseModal :open="modalOpen" :title="editing ? `Editar repuesto: ${editing.internalCode}` : 'Nuevo repuesto'" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Código Interno">
            <input
              :value="editing ? editing.internalCode : 'Se generará automáticamente (R-0001…)'"
              class="form-input bg-gray-100 text-gray-500"
              disabled
            />
          </FormField>
          <FormField label="Código de Repuesto (código de barras)" required>
            <input v-model="form.partCode" class="form-input uppercase" required maxlength="40" placeholder="5SL-E3440-00" />
          </FormField>
        </div>
        <FormField label="Descripción" required>
          <input v-model="form.description" class="form-input" required maxlength="200" />
        </FormField>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Marca">
            <CatalogSelect
              v-model="form.brandId"
              :items="brands"
              type="brands"
              add-label="Nueva marca"
              @created="brands.push($event)"
            />
          </FormField>
          <FormField label="Categoría">
            <CatalogSelect
              v-model="form.categoryId"
              :items="categories"
              type="categories"
              add-label="Nueva categoría"
              @created="categories.push($event)"
            />
          </FormField>
          <FormField label="Unidad de Medida">
            <select v-model="form.unitOfMeasure" class="form-input">
              <option v-for="u in UNITS_OF_MEASURE" :key="u" :value="u">{{ u }}</option>
            </select>
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
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Stock Mínimo">
            <input v-model.number="form.minStock" type="number" class="form-input" min="0" />
          </FormField>
          <FormField :label="`Precio Compra (${priceCurrency === 'USD' ? 'US$' : 'S/'})`">
            <input v-model.number="form.purchasePrice" type="number" step="0.01" class="form-input" min="0" />
            <p v-if="priceCurrency === 'USD' && dayRate" class="text-[10px] text-gray-400">= S/ {{ (toSoles(form.purchasePrice) ?? 0).toFixed(2) }}</p>
          </FormField>
          <FormField :label="`Precio Venta (${priceCurrency === 'USD' ? 'US$' : 'S/'})`">
            <input v-model.number="form.salePrice" type="number" step="0.01" class="form-input" min="0" />
            <p v-if="priceCurrency === 'USD' && dayRate" class="text-[10px] text-gray-400">= S/ {{ (toSoles(form.salePrice) ?? 0).toFixed(2) }}</p>
          </FormField>
          <FormField label="Moneda de precios">
            <select v-model="priceCurrency" class="form-input">
              <option value="PEN">Soles (S/)</option>
              <option value="USD">Dólares (US$){{ dayRate ? ` — T.C. ${dayRate}` : '' }}</option>
            </select>
          </FormField>
        </div>
        <!-- Registrar como compra (solo al crear): ingresa stock + costo -->
        <div v-if="!editing" class="rounded-lg border border-blue-100 bg-blue-50/50 p-3">
          <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
            <input v-model="regPurchase" type="checkbox" />
            Registrar también como compra (ingresa stock y costo)
          </label>
          <div v-if="regPurchase" class="mt-3 grid grid-cols-2 gap-4">
            <FormField label="Proveedor">
              <select v-model.number="regSupplierId" class="form-input">
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.businessName }}</option>
              </select>
            </FormField>
            <FormField label="Cantidad que ingresa">
              <input v-model.number="regQty" type="number" min="1" class="form-input" />
            </FormField>
          </div>
        </div>

        <FormField v-if="salePriceChanged" label="Motivo del cambio de precio">
          <input
            v-model="form.priceChangeReason"
            class="form-input"
            maxlength="255"
            placeholder="Opcional: p. ej. ajuste por lista de proveedor, promoción, etc."
          />
          <p class="mt-1 text-xs text-gray-500">Se registrará en el historial de precios.</p>
        </FormField>
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

    <!-- Importación masiva -->
    <BaseModal :open="importOpen" title="Importar productos desde Excel/CSV" size="xl" @close="importOpen = false">
      <div class="space-y-4">
        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
          <p class="mb-2">
            <strong>Cómo funciona:</strong> descarga la plantilla, complétala en Excel y súbela. Si un
            <strong>código</strong> ya existe, se <strong>actualiza</strong>; si no, se <strong>crea</strong>.
            El <strong>stock inicial</strong> solo aplica a productos nuevos. La plantilla trae una fila de
            ejemplo (REP-001): reemplázala o elimínala antes de subir.
          </p>
          <button class="btn-secondary" @click="downloadTemplate">Descargar plantilla</button>
        </div>

        <FormField label="Archivo (.csv o .xlsx guardado como CSV)">
          <input type="file" accept=".csv,text/csv,.xlsx" class="form-input" @change="onImportFile" />
        </FormField>

        <div class="flex flex-wrap gap-2">
          <button class="btn-secondary" :disabled="!importFile || importing" @click="runImport(true)">
            {{ importing ? 'Procesando…' : 'Previsualizar' }}
          </button>
        </div>

        <p v-if="importError" class="text-sm text-red-600">{{ importError }}</p>

        <div v-if="importResult" class="space-y-3">
          <div class="flex flex-wrap gap-2 text-sm">
            <span class="rounded-full bg-gray-100 px-3 py-1">Total: <strong>{{ importResult.summary.total }}</strong></span>
            <span class="rounded-full bg-green-100 px-3 py-1 text-green-800">Crear: <strong>{{ importResult.summary.create }}</strong></span>
            <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-800">Actualizar: <strong>{{ importResult.summary.update }}</strong></span>
            <span class="rounded-full bg-red-100 px-3 py-1 text-red-800">Errores: <strong>{{ importResult.summary.error }}</strong></span>
          </div>

          <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200">
            <table class="w-full text-left text-sm">
              <thead class="sticky top-0 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                  <th class="px-3 py-2">Fila</th>
                  <th class="px-3 py-2">Código</th>
                  <th class="px-3 py-2">Descripción</th>
                  <th class="px-3 py-2">Acción</th>
                  <th class="px-3 py-2">Detalle</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in importResult.rows" :key="r.line" class="border-t border-gray-100">
                  <td class="px-3 py-2 text-gray-500">{{ r.line }}</td>
                  <td class="px-3 py-2 font-medium">{{ r.internalCode || '—' }}</td>
                  <td class="px-3 py-2">{{ r.description || '—' }}</td>
                  <td class="px-3 py-2">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="importStatusClass[r.status]">
                      {{ importStatusText[r.status] }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-600">{{ r.message }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="importResult.committed" class="text-sm font-medium text-green-700">
            ✓ Importación aplicada. Puedes cerrar esta ventana.
          </p>
          <p v-else-if="importHasErrors" class="text-xs text-amber-700">
            Hay filas con error: se omitirán al confirmar. Corrígelas en el Excel si quieres incluirlas.
          </p>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-200 pt-3">
          <button class="btn-secondary" @click="importOpen = false">Cerrar</button>
          <button
            v-if="importResult && !importResult.committed && (importResult.summary.create + importResult.summary.update) > 0"
            class="btn-primary"
            :disabled="importing"
            @click="runImport(false)"
          >
            {{ importing ? 'Aplicando…' : `Confirmar (${importResult.summary.create + importResult.summary.update})` }}
          </button>
        </div>
      </div>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Eliminar repuesto"
      :message="`«${confirmTarget?.description}» se eliminará por completo (registro y Kardex). No se puede si tiene ventas asociadas. ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
