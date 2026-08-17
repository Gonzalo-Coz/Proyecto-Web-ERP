<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import { purchaseService } from '@/services/purchases'
import { supplierService } from '@/services/masters'
import { sparePartService } from '@/services/inventory'
import { unitService } from '@/services/motorcycles'
import { catalogService } from '@/services/catalogs'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import type { SupplierItem } from '@/types/masters'
import type { SparePartItem } from '@/types/inventory'
import type { UnitItem } from '@/types/motorcycles'
import type { CatalogItem } from '@/types/catalogs'
import { PURCHASE_DOCUMENT_TYPES, type ImportPreview, type PurchaseItemSummary, type PurchaseLine } from '@/types/purchases'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const toast = useToast()

/* ===== Importación de factura XML de Yamaha ===== */
const importOpen = ref(false)
const importLoading = ref(false)
const importing = ref(false)
const importError = ref('')
const preview = ref<ImportPreview | null>(null)
const xmlFile = ref<File | null>(null)
const pdfLoading = ref(false)

function openImport(): void {
  preview.value = null
  xmlFile.value = null
  importError.value = ''
  importOpen.value = true
}

async function onXmlSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  if (file.size === 0) {
    importError.value = 'El archivo XML está vacío (0 KB). Vuelve a descargar el XML de Yamaha y adjúntalo de nuevo.'
    input.value = ''
    return
  }
  xmlFile.value = file
  importLoading.value = true
  importError.value = ''
  try {
    preview.value = await purchaseService.importPreview(file)
    initMotoDefaults()
  } catch (e: any) {
    importError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo leer el XML.'
  } finally {
    importLoading.value = false
    input.value = ''
  }
}

/** Re-lee la factura adjuntando el PDF para autocompletar el DUA por VIN. */
async function onPdfSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file || !xmlFile.value) return
  pdfLoading.value = true
  importError.value = ''
  try {
    preview.value = await purchaseService.importPreview(xmlFile.value, file)
    initMotoDefaults()
    const filled = preview.value.motorcycles.filter((m) => m.duaNumber).length
    if (filled === 0) importError.value = 'No se encontraron DUAs en el PDF (¿es el PDF de esta misma factura?).'
  } catch (e: any) {
    importError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo leer el PDF.'
  } finally {
    pdfLoading.value = false
    input.value = ''
  }
}

/** Símbolo de moneda de la factura para las columnas de costo. */
const curSym = computed(() => (preview.value?.document.currency === 'USD' ? 'US$' : 'S/'))

/** PVP Yamaha → precio de venta con +X% (editable, por defecto 10%). */
const pvpPct = ref(10)
function applyPvp(line: { pvp?: number | null; salePrice: number | null }): void {
  if (line.pvp && line.pvp > 0) {
    line.salePrice = Math.round(line.pvp * (1 + (Number(pvpPct.value) || 0) / 100) * 100) / 100
  }
}
function applyPvpAll(): void {
  const p = preview.value
  if (!p) return
  p.spareParts.forEach(applyPvp)
}

/** Margen: % y monto entre el PVP y el precio de venta. */
function margin(base: number | null | undefined, sale: number | null): { pct: number; amount: number } | null {
  const b = Number(base ?? 0)
  if (!sale || !b || b <= 0) return null
  return { pct: ((sale - b) / b) * 100, amount: sale - b }
}

/** Motos: el precio de venta se escribe en US$ o S/; por defecto la moneda de la factura. */
function initMotoDefaults(): void {
  const p = preview.value
  if (!p) return
  const cur = p.document.currency === 'USD' ? 'USD' : 'PEN'
  p.motorcycles.forEach((m) => {
    m.saleCurrency = m.saleCurrency ?? cur
  })
}
const rate = computed(() => Number(preview.value?.exchangeRate ?? 0))
/** Costo de la moto en soles (la factura suele venir en US$). */
function motoCostSoles(m: { costPen: number }): number {
  const usd = preview.value?.document.currency === 'USD'
  return usd ? Math.round(m.costPen * rate.value * 100) / 100 : m.costPen
}
/** Precio de venta de la moto convertido a soles según la moneda elegida. */
function motoSaleSoles(m: { salePrice: number | null; saleCurrency?: 'PEN' | 'USD' }): number | null {
  if (m.salePrice === null || m.salePrice === undefined) return null
  return m.saleCurrency === 'USD' ? Math.round(m.salePrice * rate.value * 100) / 100 : m.salePrice
}
/** Margen real de la moto: costo vs. precio de venta, ambos en soles. */
function motoMargin(m: { costPen: number; salePrice: number | null; saleCurrency?: 'PEN' | 'USD' }) {
  return margin(motoCostSoles(m), motoSaleSoles(m))
}

const importTotal = computed(() => {
  const p = preview.value
  if (!p) return 0
  const sp = p.spareParts.reduce((a, s) => a + s.costPen * s.quantity, 0)
  const mt = p.motorcycles.reduce((a, m) => a + m.costPen, 0)
  return Math.round((sp + mt) * 100) / 100
})

async function confirmImport(): Promise<void> {
  if (!preview.value) return
  importing.value = true
  importError.value = ''
  try {
    // El precio de venta de motos se guarda SIEMPRE en soles (convertido si se escribió en US$).
    const p = preview.value
    const payload = {
      ...p,
      motorcycles: p.motorcycles.map((m) => ({ ...m, salePrice: motoSaleSoles(m) })),
    }
    await purchaseService.importConfirm(payload)
    importOpen.value = false
    toast.success('Factura importada: stock y unidades registrados.')
    await load()
  } catch (e: any) {
    importError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo confirmar la importación.'
  } finally {
    importing.value = false
  }
}

const columns: TableColumn[] = [
  { key: 'purchaseNumber', label: 'Número', sortable: true },
  { key: 'purchaseDate', label: 'Fecha', sortable: true },
  { key: 'supplierName', label: 'Proveedor' },
  { key: 'total', label: 'Total (S/)', sortable: true },
  { key: 'status', label: 'Estado' },
]

const rows = ref<PurchaseItemSummary[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'purchaseNumber', direction: 'desc' as const })

const suppliers = ref<SupplierItem[]>([])
const spareParts = ref<SparePartItem[]>([])
const units = ref<UnitItem[]>([])
const paymentMethods = ref<CatalogItem[]>([])

const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  supplierId: 0,
  purchaseDate: new Date().toISOString().slice(0, 10),
  documentType: 'FACTURA',
  series: null as string | null,
  documentNumber: null as string | null,
  paymentMethodId: null as number | null,
  notes: null as string | null,
})
const lines = ref<PurchaseLine[]>([])

const detail = ref<PurchaseItemSummary | null>(null)
const cancelTarget = ref<PurchaseItemSummary | null>(null)

const subtotal = computed(() =>
  lines.value.reduce((acc, l) => acc + l.quantity * l.unitPrice - l.discount, 0),
)
const igv = computed(() => Math.round(subtotal.value * 0.18 * 100) / 100)
const total = computed(() => subtotal.value + igv.value)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await purchaseService.list(query)
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

function addLine(itemType: 'SPARE_PART' | 'MOTORCYCLE_UNIT'): void {
  lines.value.push({
    itemType,
    sparePartId: itemType === 'SPARE_PART' ? (spareParts.value[0]?.id ?? null) : null,
    motorcycleUnitId: itemType === 'MOTORCYCLE_UNIT' ? (units.value[0]?.id ?? null) : null,
    quantity: 1,
    unitPrice: 0,
    discount: 0,
  })
}

function openCreate(): void {
  Object.assign(form, {
    supplierId: suppliers.value[0]?.id ?? 0,
    purchaseDate: new Date().toISOString().slice(0, 10),
    documentType: 'FACTURA',
    series: null,
    documentNumber: null,
    paymentMethodId: null,
    notes: null,
  })
  lines.value = []
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  if (lines.value.length === 0) {
    formError.value = 'Agrega al menos una línea.'
    return
  }
  saving.value = true
  formError.value = ''
  try {
    await purchaseService.create({ ...form, items: lines.value })
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo registrar la compra.'
  } finally {
    saving.value = false
  }
}

async function openDetail(row: PurchaseItemSummary): Promise<void> {
  detail.value = await purchaseService.get(row.id)
}

async function confirmCancel(): Promise<void> {
  if (!cancelTarget.value) return
  try {
    await purchaseService.cancel(cancelTarget.value.id)
    cancelTarget.value = null
    await load()
  } catch (error: any) {
    alert(error.response?.data?.detail ?? 'No se pudo anular la compra.')
    cancelTarget.value = null
  }
}

onMounted(async () => {
  await load()
  suppliers.value = (await supplierService.list({ page: 1, perPage: 100, search: '', sort: 'businessName', direction: 'asc' })).data.filter((s) => s.isActive)
  spareParts.value = (await sparePartService.list({ page: 1, perPage: 100, search: '', sort: 'description', direction: 'asc' })).data.filter((p) => p.isActive)
  units.value = (await unitService.list({ page: 1, perPage: 100, search: '', sort: 'internalCode', direction: 'asc' })).data.filter((u) => u.status !== 'VENDIDA' && u.status !== 'BAJA')
  paymentMethods.value = (await catalogService.list('payment_methods')).filter((m) => m.isActive)
})
</script>

<template>
  <DefaultLayout>
    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por número, proveedor o documento…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('purchases.list.create')" class="btn-secondary" @click="openImport">
          Importar factura XML
        </button>
        <button v-if="auth.can('purchases.list.create')" class="btn-primary" @click="openCreate">
          Nueva compra
        </button>
      </template>
      <template #cell-status="{ row }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="row.status === 'REGISTRADA' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
        >
          {{ row.status }}
        </span>
      </template>
      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button class="btn-secondary" @click="openDetail(row as unknown as PurchaseItemSummary)">Ver</button>
          <button
            v-if="auth.can('purchases.list.cancel') && row.status === 'REGISTRADA'"
            class="btn-secondary !text-red-600"
            @click="cancelTarget = row as unknown as PurchaseItemSummary"
          >
            Anular
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Nueva compra -->
    <BaseModal :open="modalOpen" title="Nueva compra" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Proveedor" required>
            <select v-model.number="form.supplierId" class="form-input" required>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.businessName }}</option>
            </select>
          </FormField>
          <FormField label="Fecha" required>
            <input v-model="form.purchaseDate" type="date" class="form-input" required />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Tipo Doc." required>
            <select v-model="form.documentType" class="form-input" required>
              <option v-for="d in PURCHASE_DOCUMENT_TYPES" :key="d" :value="d">{{ d }}</option>
            </select>
          </FormField>
          <FormField label="Serie">
            <input v-model="form.series" class="form-input" maxlength="10" placeholder="F001" />
          </FormField>
          <FormField label="Número">
            <input v-model="form.documentNumber" class="form-input" maxlength="20" />
          </FormField>
        </div>
        <FormField label="Forma de Pago">
          <select v-model.number="form.paymentMethodId" class="form-input">
            <option :value="null">—</option>
            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </FormField>

        <!-- Líneas -->
        <div class="rounded-lg border border-gray-200 p-3">
          <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-700">Detalle</p>
            <div class="flex gap-2">
              <button type="button" class="btn-secondary" @click="addLine('SPARE_PART')">+ Repuesto</button>
              <button type="button" class="btn-secondary" @click="addLine('MOTORCYCLE_UNIT')">+ Motocicleta</button>
            </div>
          </div>
          <p v-if="lines.length === 0" class="py-4 text-center text-sm text-gray-400">Sin líneas.</p>
          <div v-for="(line, i) in lines" :key="i" class="mb-2 grid grid-cols-12 items-end gap-2 border-t border-gray-100 pt-2">
            <div class="col-span-5">
              <label class="form-label text-xs">{{ line.itemType === 'SPARE_PART' ? 'Repuesto' : 'Unidad (VIN)' }}</label>
              <select v-if="line.itemType === 'SPARE_PART'" v-model.number="line.sparePartId" class="form-input">
                <option v-for="p in spareParts" :key="p.id" :value="p.id">{{ p.internalCode }} — {{ p.description }}</option>
              </select>
              <select v-else v-model.number="line.motorcycleUnitId" class="form-input">
                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.internalCode }} — {{ u.modelName }} ({{ u.vin }})</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="form-label text-xs">Cant.</label>
              <input v-model.number="line.quantity" type="number" min="1" class="form-input" :disabled="line.itemType === 'MOTORCYCLE_UNIT'" />
            </div>
            <div class="col-span-2">
              <label class="form-label text-xs">P. Unit.</label>
              <input v-model.number="line.unitPrice" type="number" step="0.01" min="0" class="form-input" />
            </div>
            <div class="col-span-2">
              <label class="form-label text-xs">Dscto.</label>
              <input v-model.number="line.discount" type="number" step="0.01" min="0" class="form-input" />
            </div>
            <div class="col-span-1">
              <button type="button" class="btn-secondary !px-2 !text-red-600" @click="lines.splice(i, 1)">✕</button>
            </div>
          </div>
          <div v-if="lines.length" class="mt-3 space-y-1 border-t border-gray-200 pt-3 text-right text-sm">
            <p>Subtotal: <strong>S/ {{ subtotal.toFixed(2) }}</strong></p>
            <p>IGV (18%): <strong>S/ {{ igv.toFixed(2) }}</strong></p>
            <p class="text-base">Total: <strong>S/ {{ total.toFixed(2) }}</strong></p>
          </div>
        </div>

        <FormField label="Observaciones">
          <input v-model="form.notes" class="form-input" />
        </FormField>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Registrando…' : 'Registrar compra' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Detalle -->
    <BaseModal :open="detail !== null" :title="`Compra ${detail?.purchaseNumber}`" @close="detail = null">
      <div v-if="detail" class="space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-2 text-gray-600">
          <p>Proveedor: <strong class="text-gray-900">{{ detail.supplierName }}</strong></p>
          <p>Fecha: <strong class="text-gray-900">{{ detail.purchaseDate }}</strong></p>
          <p>Documento: <strong class="text-gray-900">{{ detail.documentType }} {{ detail.series }}-{{ detail.documentNumber }}</strong></p>
          <p>Estado: <strong class="text-gray-900">{{ detail.status }}</strong></p>
        </div>
        <table class="w-full text-left">
          <thead class="text-xs uppercase text-gray-500">
            <tr><th class="py-1">Descripción</th><th class="py-1 text-right">Cant.</th><th class="py-1 text-right">P.Unit</th><th class="py-1 text-right">Total</th></tr>
          </thead>
          <tbody>
            <tr v-for="i in detail.items" :key="i.id" class="border-t border-gray-100">
              <td class="py-1">{{ i.description }}</td>
              <td class="py-1 text-right">{{ i.quantity }}</td>
              <td class="py-1 text-right">{{ i.unitPrice }}</td>
              <td class="py-1 text-right">{{ i.lineTotal }}</td>
            </tr>
          </tbody>
        </table>
        <div class="border-t border-gray-200 pt-2 text-right">
          <p>Subtotal: S/ {{ detail.subtotal }} · IGV: S/ {{ detail.igv }} · <strong>Total: S/ {{ detail.total }}</strong></p>
        </div>
      </div>
    </BaseModal>

    <ConfirmDialog
      :open="cancelTarget !== null"
      title="Anular compra"
      :message="`La compra ${cancelTarget?.purchaseNumber} se anulará y el stock de los repuestos comprados se revertirá en el Kardex. ¿Continuar?`"
      confirm-label="Anular"
      danger
      @confirm="confirmCancel"
      @cancel="cancelTarget = null"
    />

    <!-- Importar factura XML de Yamaha -->
    <BaseModal :open="importOpen" title="Importar factura XML (Yamaha)" size="xl" @close="importOpen = false">
      <div class="space-y-4">
        <div v-if="!preview" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center">
          <p class="mb-3 text-sm text-gray-600">Sube el archivo <strong>.xml</strong> de la factura electrónica de Yamaha.</p>
          <label class="btn-primary cursor-pointer">
            {{ importLoading ? 'Leyendo…' : 'Seleccionar XML' }}
            <input type="file" accept=".xml,text/xml,application/xml" class="hidden" @change="onXmlSelected" />
          </label>
        </div>

        <template v-if="preview">
          <!-- Cabecera -->
          <div class="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3 text-sm sm:grid-cols-4">
            <div><span class="text-gray-500">Proveedor</span><br /><strong>{{ preview.supplier.name }}</strong></div>
            <div><span class="text-gray-500">Documento</span><br /><strong>{{ preview.document.fullNumber }}</strong></div>
            <div><span class="text-gray-500">Fecha</span><br /><strong>{{ preview.document.issueDate }}</strong></div>
            <div>
              <span class="text-gray-500">Moneda</span><br />
              <strong>{{ preview.document.currency }}</strong>
              <span v-if="preview.supplier.existingId === null" class="ml-1 text-xs text-amber-600">(proveedor nuevo)</span>
            </div>
          </div>

          <p v-if="preview.document.currency === 'USD'" class="rounded-lg border border-blue-100 bg-blue-50 p-2 text-xs text-blue-700">
            Factura en dólares: el costo se guarda tal cual en US$ (sin convertir). La conversión a soles solo se hace al vender.
          </p>

          <div v-if="preview.spareParts.length" class="flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50/50 p-2 text-sm">
            <span class="font-medium text-emerald-800">% sobre PVP Yamaha (repuestos):</span>
            <input v-model.number="pvpPct" type="number" step="0.5" class="form-input !w-20 !py-1 !text-xs" @input="applyPvpAll" />
            <span class="text-xs text-emerald-700">Al escribir el PVP de cada repuesto, su precio de venta se calcula con este %.</span>
          </div>

          <!-- Motos -->
          <div v-if="preview.motorcycles.length">
            <div class="mb-2 flex items-center justify-between">
              <h3 class="text-sm font-bold text-gray-700">Motocicletas ({{ preview.motorcycles.length }})</h3>
              <label class="btn-secondary cursor-pointer !py-1 !text-xs">
                {{ pdfLoading ? 'Leyendo PDF…' : 'Adjuntar PDF para DUA' }}
                <input type="file" accept="application/pdf,.pdf" class="hidden" @change="onPdfSelected" />
              </label>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-left text-gray-500">
                  <tr><th class="px-2 py-1">Modelo</th><th class="px-2 py-1">Color</th><th class="px-2 py-1">VIN</th><th class="px-2 py-1">Motor</th><th class="px-2 py-1">DUA / Ítem</th><th class="px-2 py-1 text-right">Costo {{ curSym }}</th><th class="px-2 py-1 text-right">P. Venta</th><th class="px-2 py-1 text-right">Margen S/</th><th class="px-2 py-1"></th></tr>
                </thead>
                <tbody>
                  <tr v-for="(m, i) in preview.motorcycles" :key="i" class="border-t border-gray-100" :class="m.alreadyExists ? 'bg-red-50' : ''">
                    <td class="px-2 py-1"><input v-model="m.model" class="form-input !py-1 !text-xs" /></td>
                    <td class="px-2 py-1"><input v-model="m.color" class="form-input !w-20 !py-1 !text-xs" /></td>
                    <td class="px-2 py-1 font-mono">{{ m.vin }}<br v-if="m.alreadyExists" /><span v-if="m.alreadyExists" class="text-red-600">ya existe</span></td>
                    <td class="px-2 py-1 font-mono">{{ m.engine }}</td>
                    <td class="px-2 py-1"><div class="flex gap-1"><input v-model="m.duaNumber" placeholder="DUA" class="form-input !w-20 !py-1 !text-xs" /><input v-model="m.duaItem" placeholder="Ítem" class="form-input !w-14 !py-1 !text-xs" /></div></td>
                    <td class="px-2 py-1 text-right"><input v-model.number="m.costPen" type="number" step="0.01" class="form-input !w-24 !py-1 !text-right !text-xs" /></td>
                    <td class="px-2 py-1 text-right">
                      <div class="flex items-center justify-end gap-1">
                        <input v-model.number="m.salePrice" type="number" step="0.01" min="0" placeholder="—" class="form-input !w-24 !py-1 !text-right !text-xs" />
                        <select v-model="m.saleCurrency" class="form-input !w-16 !py-1 !text-xs">
                          <option value="PEN">S/</option>
                          <option value="USD">US$</option>
                        </select>
                      </div>
                      <div v-if="m.saleCurrency === 'USD' && motoSaleSoles(m)" class="mt-0.5 text-[10px] text-gray-400">= S/ {{ motoSaleSoles(m)!.toFixed(2) }}</div>
                    </td>
                    <td class="px-2 py-1 text-right text-xs">
                      <template v-if="motoMargin(m)">
                        <span :class="motoMargin(m)!.pct >= 0 ? 'text-green-600' : 'text-red-600'">
                          {{ motoMargin(m)!.pct.toFixed(0) }}% · S/ {{ motoMargin(m)!.amount.toFixed(2) }}
                        </span>
                      </template>
                      <span v-else class="text-gray-300">—</span>
                    </td>
                    <td class="px-2 py-1 text-center">
                      <button type="button" class="font-bold text-red-600 hover:text-red-800" title="Quitar esta moto del import" @click="preview.motorcycles.splice(i, 1)">✕</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Repuestos -->
          <div v-if="preview.spareParts.length">
            <h3 class="mb-2 text-sm font-bold text-gray-700">Repuestos ({{ preview.spareParts.length }})</h3>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-left text-gray-500">
                  <tr><th class="px-2 py-1">Código</th><th class="px-2 py-1">Descripción</th><th class="px-2 py-1 text-right">Cant.</th><th class="px-2 py-1 text-right">Costo {{ curSym }}</th><th class="px-2 py-1 text-right">PVP {{ curSym }}</th><th class="px-2 py-1 text-right">P. Venta {{ curSym }}</th><th class="px-2 py-1 text-right">Margen</th><th class="px-2 py-1">Estado</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(s, i) in preview.spareParts" :key="i" class="border-t border-gray-100">
                    <td class="px-2 py-1 font-mono">{{ s.code }}</td>
                    <td class="px-2 py-1"><input v-model="s.description" class="form-input !py-1 !text-xs" /></td>
                    <td class="px-2 py-1 text-right"><input v-model.number="s.quantity" type="number" min="1" class="form-input !w-16 !py-1 !text-right !text-xs" /></td>
                    <td class="px-2 py-1 text-right"><input v-model.number="s.costPen" type="number" step="0.01" class="form-input !w-24 !py-1 !text-right !text-xs" /></td>
                    <td class="px-2 py-1 text-right"><input v-model.number="s.pvp" type="number" step="0.01" min="0" placeholder="PVP" class="form-input !w-24 !py-1 !text-right !text-xs" @input="applyPvp(s)" /></td>
                    <td class="px-2 py-1 text-right"><input v-model.number="s.salePrice" type="number" step="0.01" min="0" placeholder="—" class="form-input !w-24 !py-1 !text-right !text-xs" /></td>
                    <td class="px-2 py-1 text-right text-xs">
                      <template v-if="margin(s.pvp, s.salePrice)">
                        <span :class="margin(s.pvp, s.salePrice)!.pct >= 0 ? 'text-green-600' : 'text-red-600'">
                          {{ margin(s.pvp, s.salePrice)!.pct.toFixed(0) }}% · S/ {{ margin(s.pvp, s.salePrice)!.amount.toFixed(2) }}
                        </span>
                      </template>
                      <span v-else class="text-gray-300">—</span>
                    </td>
                    <td class="px-2 py-1">
                      <span v-if="s.existingId" class="text-green-600">existe (stock {{ s.existingStock }}) +{{ s.quantity }}</span>
                      <span v-else class="text-blue-600">nuevo</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex items-center justify-between border-t border-gray-200 pt-3">
            <p class="text-sm text-gray-600">Total (costo, tal cual factura): <strong>{{ curSym }} {{ importTotal.toFixed(2) }}</strong></p>
            <p class="text-xs text-gray-400">Se registrará como Compra; el stock y las unidades entran al sistema.</p>
          </div>
        </template>

        <p v-if="importError" class="text-sm text-red-600">{{ importError }}</p>

        <div class="flex justify-end gap-3 border-t border-gray-100 pt-3">
          <button type="button" class="btn-secondary" @click="importOpen = false">Cancelar</button>
          <button
            v-if="preview"
            type="button"
            class="btn-primary"
            :disabled="importing"
            @click="confirmImport"
          >
            {{ importing ? 'Importando…' : 'Confirmar e importar' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
