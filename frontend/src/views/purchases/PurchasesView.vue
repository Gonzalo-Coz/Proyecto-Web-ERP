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
import { PURCHASE_DOCUMENT_TYPES, type PurchaseItemSummary, type PurchaseLine } from '@/types/purchases'

const auth = useAuthStore()

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
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'purchaseDate', direction: 'desc' as const })

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
  </DefaultLayout>
</template>
