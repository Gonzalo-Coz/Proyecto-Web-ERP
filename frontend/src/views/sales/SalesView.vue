<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import UbigeoSelect from '@/components/ui/UbigeoSelect.vue'
import api from '@/services/api'
import { saleService } from '@/services/sales'
import { customerService, customerTypeService } from '@/services/masters'
import { sparePartService } from '@/services/inventory'
import { unitService } from '@/services/motorcycles'
import { catalogService } from '@/services/catalogs'
import { pricingService } from '@/services/pricing'
import { promotionService } from '@/services/promotions'
import { lookupService } from '@/services/lookup'
import type { PromotionItem } from '@/types/promotions'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import { DOCUMENT_TYPES, type CustomerItem, type CustomerTypeItem } from '@/types/masters'
import type { PriceListItem } from '@/types/pricing'
import type { SparePartItem } from '@/types/inventory'
import type { UnitItem } from '@/types/motorcycles'
import type { CatalogItem } from '@/types/catalogs'
import { type SaleLine, type SaleStatus, type SaleSummary } from '@/types/sales'
import { printCotizacion } from '@/utils/comprobante'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'saleNumber', label: 'Número', sortable: true },
  { key: 'saleDate', label: 'Fecha', sortable: true },
  { key: 'customerName', label: 'Cliente' },
  { key: 'total', label: 'Total (S/)', sortable: true },
  { key: 'balance', label: 'Saldo' },
  { key: 'status', label: 'Estado', sortable: true },
]

const STATUS_COLORS: Record<SaleStatus, string> = {
  COTIZACION: 'bg-gray-200 text-gray-700',
  RESERVA: 'bg-yellow-100 text-yellow-800',
  COMPLETADA: 'bg-green-100 text-green-800',
  ANULADA: 'bg-red-100 text-red-800',
}

const rows = ref<SaleSummary[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const statusFilter = ref('')
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'saleDate', direction: 'desc' as const })

const customers = ref<CustomerItem[]>([])
const customerTypes = ref<CustomerTypeItem[]>([])
const priceLists = ref<PriceListItem[]>([])
const spareParts = ref<SparePartItem[]>([])
const units = ref<UnitItem[]>([])
const paymentMethods = ref<CatalogItem[]>([])

const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  customerId: 0,
  saleDate: new Date().toISOString().slice(0, 10),
  complete: false,
  notes: null as string | null,
  igvIncluded: true,
  igvExempt: false,
})
const lines = ref<SaleLine[]>([])
const genericCustomerId = ref<number | null>(null)

/** Boleta simple: selecciona el cliente "Público General" (venta sin pedir DNI/RUC). */
function usePublicoGeneral(): void {
  if (genericCustomerId.value) {
    form.customerId = genericCustomerId.value
    onCustomerChange()
  }
}

// --- Alta rápida de cliente desde la venta (sin cerrar la venta en curso) ---
const toast = useToast()
const customerModalOpen = ref(false)
const newCustSaving = ref(false)
const newCustLookingUp = ref(false)
const newCustError = ref('')
const newCustEmpty = {
  documentType: 'DNI' as (typeof DOCUMENT_TYPES)[number],
  documentNumber: '',
  name: '',
  tradeName: null as string | null,
  address: null as string | null,
  district: null as string | null,
  province: null as string | null,
  department: null as string | null,
  phone: null as string | null,
  mobile: null as string | null,
  email: null as string | null,
  customerTypeId: null as number | null,
  priceListId: null as number | null,
  isActive: true,
}
const newCust = reactive({ ...newCustEmpty })

function openNewCustomer(): void {
  Object.assign(newCust, newCustEmpty)
  newCustError.value = ''
  customerModalOpen.value = true
}

async function lookupNewCustomer(): Promise<void> {
  const doc = newCust.documentNumber.trim()
  if (newCust.documentType !== 'DNI' && newCust.documentType !== 'RUC') {
    toast.info('La consulta automática solo aplica a DNI y RUC.')
    return
  }
  newCustLookingUp.value = true
  try {
    if (newCust.documentType === 'DNI') {
      newCust.name = (await lookupService.dni(doc)).nombreCompleto
    } else {
      const c = await lookupService.ruc(doc)
      newCust.name = c.razonSocial
      newCust.tradeName = c.nombreComercial ?? newCust.tradeName
      newCust.address = c.direccion ?? newCust.address
      newCust.district = c.distrito ?? newCust.district
      newCust.province = c.provincia ?? newCust.province
      newCust.department = c.departamento ?? newCust.department
    }
    toast.success('Datos cargados.')
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? 'No se pudo consultar el documento.')
  } finally {
    newCustLookingUp.value = false
  }
}

async function saveNewCustomer(): Promise<void> {
  newCustSaving.value = true
  newCustError.value = ''
  try {
    const created = await customerService.create({
      documentType: newCust.documentType,
      documentNumber: newCust.documentNumber.trim(),
      name: newCust.name.trim(),
      tradeName: newCust.tradeName,
      address: newCust.address,
      district: newCust.district,
      province: newCust.province,
      department: newCust.department,
      phone: newCust.phone,
      mobile: newCust.mobile,
      email: newCust.email,
      priceListId: newCust.priceListId,
      customerTypeId: newCust.customerTypeId,
      isActive: newCust.isActive,
    })
    customers.value.unshift(created)
    form.customerId = created.id
    onCustomerChange()
    customerModalOpen.value = false
    toast.success('Cliente creado y seleccionado.')
  } catch (e: any) {
    newCustError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo crear el cliente.'
  } finally {
    newCustSaving.value = false
  }
}

const detail = ref<SaleSummary | null>(null)
const payForm = reactive({ amount: 0, paymentMethodId: null as number | null, reference: '' })
const payError = ref('')
const cancelTarget = ref<SaleSummary | null>(null)
const actionError = ref('')

/** Neto de la línea: bruto − descuento porcentual (recalcula en vivo). */
/** Etiqueta completa de la unidad para el selector: modelo, serie, motor, año, color y DUA. */
function unitLabel(u: UnitItem): string {
  const parts = [`Serie: ${u.vin}`]
  if (u.engineNumber) parts.push(`Motor: ${u.engineNumber}`)
  const year = u.manufactureYear ?? u.modelYear
  if (year) parts.push(`Año Modelo: ${year}`)
  if (u.color) parts.push(`Color: ${u.color}`)
  if (u.duaNumber) parts.push(`DUA: ${u.duaNumber}`)
  if (u.duaItem) parts.push(`Item DUA: ${u.duaItem}`)
  return `${u.internalCode} — ${u.modelName} · ${parts.join(' | ')}`
}

/** Tipo de cambio del día (venta) para convertir precios en US$ a soles. */
const saleRate = ref<number | null>(null)

/** Precio unitario en soles: si la línea está en US$, se multiplica por el T.C. */
function linePriceSoles(l: SaleLine): number {
  return l._usd && saleRate.value ? Math.round(l.unitPrice * saleRate.value * 100) / 100 : l.unitPrice
}

function lineNet(l: SaleLine): number {
  const gross = l.quantity * linePriceSoles(l)
  return Math.max(0, gross - (gross * (l.discountPercent || 0)) / 100)
}

// Precios CON IGV incluido: la suma de líneas es el TOTAL; la base (op. gravada)
// y el IGV se calculan hacia atrás (vista previa con 18%; el backend usa la tasa
// configurada en el sistema).
const r2 = (n: number): number => Math.round(n * 100) / 100
const sumLines = computed(() => lines.value.reduce((a, l) => a + lineNet(l), 0))
// Local: precio con IGV → se extrae. Exterior: IGV se agrega. Amazonía: exonerado (IGV 0).
const subtotal = computed(() =>
  form.igvExempt ? r2(sumLines.value) : form.igvIncluded ? r2(sumLines.value / 1.18) : r2(sumLines.value),
)
const igv = computed(() =>
  form.igvExempt ? 0 : form.igvIncluded ? r2(sumLines.value - subtotal.value) : r2(sumLines.value * 0.18),
)
const total = computed(() =>
  form.igvExempt ? r2(sumLines.value) : form.igvIncluded ? r2(sumLines.value) : r2(subtotal.value + igv.value),
)

/** Zona de venta: mapea a los flags igvIncluded/igvExempt. */
const zoneMode = computed<'LOCAL' | 'AMAZONIA' | 'EXTERIOR'>({
  get: () => (form.igvExempt ? 'AMAZONIA' : form.igvIncluded ? 'LOCAL' : 'EXTERIOR'),
  set: (v) => {
    form.igvExempt = v === 'AMAZONIA'
    form.igvIncluded = v === 'LOCAL'
  },
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await saleService.list({ ...query, status: statusFilter.value })
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

function addLine(itemType: SaleLine['itemType']): void {
  const line: SaleLine = {
    itemType,
    sparePartId: itemType === 'SPARE_PART' ? (spareParts.value[0]?.id ?? null) : null,
    motorcycleUnitId: itemType === 'MOTORCYCLE_UNIT' ? (units.value[0]?.id ?? null) : null,
    description: itemType === 'SERVICE' ? '' : null,
    quantity: 1,
    unitPrice: 0,
    discountPercent: currentCustomerDiscount(),
  }
  lines.value.push(line)
  void resolveLinePrice(line)
}

// --- Promoción opcional (simple): un descuento con nombre que se aplica a la venta ---
const promotions = ref<PromotionItem[]>([])
const selectedPromoId = ref<number | null>(null)

/** Aplica el % de la promoción elegida a todas las líneas (o lo quita si "Ninguna"). */
function applyPromotion(): void {
  const promo = promotions.value.find((p) => p.id === selectedPromoId.value)
  const pct = promo && promo.discountPercent !== null ? Number(promo.discountPercent) : 0
  lines.value.forEach((l) => {
    l.discountPercent = pct
  })
}

function subjectOf(line: SaleLine): { subjectType: string; subjectId: number | null } {
  if (line.itemType === 'SPARE_PART') return { subjectType: 'spare_part', subjectId: line.sparePartId }
  if (line.itemType === 'MOTORCYCLE_UNIT') {
    return { subjectType: 'motorcycle_model', subjectId: units.value.find((u) => u.id === line.motorcycleUnitId)?.modelId ?? null }
  }
  return { subjectType: '', subjectId: null }
}

/**
 * A4: prellena el precio de la línea con el de la lista del cliente (o el base).
 * No pisa un precio escrito a mano (> 0) salvo cambio explícito (force).
 */
async function resolveLinePrice(line: SaleLine, force = false): Promise<void> {
  const { subjectType, subjectId } = subjectOf(line)
  if (subjectType === '' || subjectId === null) return
  if (!force && line.unitPrice > 0) return
  try {
    const { price } = await pricingService.resolve(subjectType, subjectId, form.customerId || undefined)
    if (price !== null) line.unitPrice = Number(price)
  } catch {
    /* si falla la resolución, el usuario ingresa el precio manualmente */
  }
}

/** Precio de venta propio del producto seleccionado (unidad de moto o ficha de repuesto). */
function ownSalePrice(line: SaleLine): number | null {
  if (line.itemType === 'SPARE_PART' && line.sparePartId) {
    const p = spareParts.value.find((x) => x.id === line.sparePartId)
    return p?.salePrice != null && p.salePrice !== '' ? Number(p.salePrice) : null
  }
  if (line.itemType === 'MOTORCYCLE_UNIT' && line.motorcycleUnitId) {
    const u = units.value.find((x) => x.id === line.motorcycleUnitId)
    return u?.salePrice != null && u.salePrice !== '' ? Number(u.salePrice) : null
  }
  return null
}

/**
 * Al cambiar el producto de una línea: coloca su precio de venta.
 * Prioriza el precio propio del producto (moto = unidad, repuesto = ficha); si no
 * tiene, intenta la lista de precios del cliente.
 */
function onLineProductChange(line: SaleLine): void {
  const own = ownSalePrice(line)
  if (own !== null && own > 0) {
    line.unitPrice = own
    line._usd = false // el precio propio ya está en soles
  } else {
    void resolveLinePrice(line, true)
  }
}

/** % de descuento por defecto del cliente seleccionado (según su tipo). */
function currentCustomerDiscount(): number {
  const c = customers.value.find((x) => x.id === form.customerId)
  return c ? Number(c.discountPercent ?? 0) : 0
}

/**
 * Al cambiar el cliente: re-resuelve precios y aplica el descuento por tipo
 * de cliente a todas las líneas (queda editable por línea).
 */
function onCustomerChange(): void {
  const pct = currentCustomerDiscount()
  lines.value.forEach((l) => {
    void resolveLinePrice(l, true)
    l.discountPercent = pct
  })
  selectedPromoId.value = null
}

function openCreate(complete: boolean): void {
  Object.assign(form, {
    customerId: customers.value[0]?.id ?? 0,
    saleDate: new Date().toISOString().slice(0, 10),
    complete,
    notes: null,
    globalDiscount: 0,
    globalDiscountIsPercent: false,
    igvIncluded: true,
    igvExempt: false,
  })
  lines.value = []
  selectedPromoId.value = null
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
    // Convierte a soles los precios en US$ antes de enviar (el backend trabaja en soles).
    const items = lines.value.map((l) => ({
      itemType: l.itemType,
      sparePartId: l.sparePartId,
      motorcycleUnitId: l.motorcycleUnitId,
      description: l.description,
      quantity: l.quantity,
      unitPrice: linePriceSoles(l),
      discountPercent: l.discountPercent,
    }))
    await saleService.create({ ...form, items })
    modalOpen.value = false
    await load()
  } catch (e: any) {
    formError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo registrar.'
  } finally {
    saving.value = false
  }
}

async function openDetail(row: SaleSummary): Promise<void> {
  detail.value = await saleService.get(row.id)
  payForm.amount = Number(detail.value.balance)
  payForm.paymentMethodId = null
  payForm.reference = ''
  payError.value = ''
}

async function doAction(action: 'reserve' | 'complete'): Promise<void> {
  if (!detail.value) return
  actionError.value = ''
  try {
    detail.value = action === 'reserve'
      ? await saleService.reserve(detail.value.id, null)
      : await saleService.complete(detail.value.id)
    await load()
  } catch (e: any) {
    payError.value = e.response?.data?.detail ?? 'No se pudo ejecutar la acción.'
  }
}

async function doPayment(): Promise<void> {
  if (!detail.value) return
  payError.value = ''
  try {
    detail.value = await saleService.addPayment(detail.value.id, payForm.amount, payForm.paymentMethodId, payForm.reference || null)
    await load()
  } catch (e: any) {
    payError.value = e.response?.data?.detail ?? 'No se pudo registrar el cobro (¿caja abierta?).'
  }
}

async function confirmCancel(): Promise<void> {
  if (!cancelTarget.value) return
  actionError.value = ''
  try {
    await saleService.cancel(cancelTarget.value.id)
  } catch (e: any) {
    actionError.value = e.response?.data?.detail ?? 'No se pudo anular.'
  } finally {
    cancelTarget.value = null
    detail.value = null
    await load()
  }
}

onMounted(async () => {
  await load()
  customers.value = (await customerService.list({ page: 1, perPage: 100, search: '', sort: 'name', direction: 'asc' })).data.filter((c) => c.isActive)
  // Cliente genérico "Público General" (boleta simple): garantiza que esté disponible arriba de la lista.
  try {
    const generic = await customerService.generic()
    genericCustomerId.value = generic.id
    if (!customers.value.some((c) => c.id === generic.id)) {
      customers.value.unshift(generic)
    }
  } catch {
    genericCustomerId.value = null
  }
  spareParts.value = (await sparePartService.list({ page: 1, perPage: 100, search: '', sort: 'description', direction: 'asc' })).data.filter((p) => p.isActive)
  units.value = (await unitService.list({ page: 1, perPage: 100, search: '', sort: 'internalCode', direction: 'asc', status: 'DISPONIBLE' })).data
  paymentMethods.value = (await catalogService.list('payment_methods')).filter((m) => m.isActive)
  try {
    customerTypes.value = await customerTypeService.list()
  } catch {
    customerTypes.value = []
  }
  try {
    priceLists.value = (await pricingService.listPriceLists({ perPage: 100 })).data.filter((l) => l.isActive)
  } catch {
    priceLists.value = []
  }
  try {
    promotions.value = (await promotionService.list({ perPage: 100 })).data.filter((p) => p.isActive)
  } catch {
    promotions.value = []
  }
  try {
    saleRate.value = (await api.get('/exchange-rate')).data.sell
  } catch {
    saleRate.value = null
  }
})
</script>

<template>
  <DefaultLayout>
    <p v-if="actionError" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ actionError }}</p>

    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por número, cliente o documento…"
      @change="onTableChange"
    >
      <template #toolbar>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="statusFilter" class="form-input w-48" @change="load()">
            <option value="">Todos los estados</option>
            <option value="COTIZACION">Cotizaciones</option>
            <option value="COMPLETADA">Ventas completadas</option>
            <option value="ANULADA">Anuladas / rechazadas</option>
          </select>
          <div v-if="auth.can('sales.list.create')" class="flex gap-2">
            <button class="btn-secondary" @click="openCreate(false)">Nueva cotización</button>
            <button class="btn-primary" @click="openCreate(true)">Venta directa</button>
          </div>
        </div>
      </template>
      <template #cell-status="{ row }">
        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="STATUS_COLORS[(row as unknown as SaleSummary).status]">
          {{ row.status }}
        </span>
      </template>
      <template #cell-balance="{ row }">
        <span :class="Number(row.balance) > 0 && row.status === 'COMPLETADA' ? 'font-semibold text-red-600' : 'text-gray-600'">
          S/ {{ row.balance }}
        </span>
      </template>
      <template #actions="{ row }">
        <button class="btn-secondary" @click="openDetail(row as unknown as SaleSummary)">Ver</button>
      </template>
    </DataTable>

    <!-- Crear cotización / venta directa -->
    <BaseModal :open="modalOpen" :title="form.complete ? 'Venta directa' : 'Nueva cotización'" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Cliente" required class="col-span-2">
            <div class="flex gap-2">
              <select v-model.number="form.customerId" class="form-input" required @change="onCustomerChange">
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.documentNumber }})</option>
              </select>
              <button
                v-if="genericCustomerId"
                type="button"
                class="btn-secondary whitespace-nowrap"
                title="Boleta simple: venta a Público General, sin pedir DNI/RUC (válido hasta S/ 700)"
                @click="usePublicoGeneral"
              >
                Público general
              </button>
              <button
                type="button"
                class="btn-secondary whitespace-nowrap"
                title="Crear un cliente nuevo sin salir de la venta"
                @click="openNewCustomer"
              >
                + Cliente
              </button>
            </div>
            <p v-if="currentCustomerDiscount() > 0" class="mt-1 text-xs font-medium text-green-600">
              Descuento por tipo de cliente: {{ currentCustomerDiscount() }}% aplicado a las líneas (editable).
            </p>
          </FormField>
          <FormField label="Fecha" required class="col-span-1">
            <input v-model="form.saleDate" type="date" class="form-input" required />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Zona / IGV">
            <select v-model="zoneMode" class="form-input">
              <option value="LOCAL">Local (Tingo María) — IGV incluido</option>
              <option value="AMAZONIA">Amazonía — exonerado de IGV</option>
              <option value="EXTERIOR">Exterior / fuera de zona — IGV agregado</option>
            </select>
          </FormField>
          <FormField v-if="promotions.length" label="Promoción (opcional)">
            <select v-model.number="selectedPromoId" class="form-input" @change="applyPromotion">
              <option :value="null">Ninguna</option>
              <option v-for="p in promotions" :key="p.id" :value="p.id">
                {{ p.name }} ({{ Number(p.discountPercent).toFixed(0) }}% dcto.)
              </option>
            </select>
          </FormField>
        </div>

        <div class="rounded-lg border border-gray-200 p-3">
          <div class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-gray-700">Detalle</p>
              <span class="text-xs text-gray-500">· T.C. (S/ por US$):</span>
              <input v-model.number="saleRate" type="number" step="0.001" min="0" placeholder="—" class="form-input !w-20 !py-0.5 !text-xs" />
            </div>
            <div class="flex gap-2">
              <button type="button" class="btn-secondary" @click="addLine('MOTORCYCLE_UNIT')">+ Moto</button>
              <button type="button" class="btn-secondary" @click="addLine('SPARE_PART')">+ Repuesto</button>
              <button type="button" class="btn-secondary" @click="addLine('SERVICE')">+ Servicio / texto libre</button>
            </div>
          </div>
          <p v-if="lines.length === 0" class="py-4 text-center text-sm text-gray-400">Sin líneas.</p>
          <div v-for="(line, i) in lines" :key="i" class="mb-2 grid grid-cols-12 items-end gap-2 border-t border-gray-100 pt-2">
            <div class="col-span-5">
              <label class="form-label text-xs">
                {{ line.itemType === 'SPARE_PART' ? 'Repuesto' : line.itemType === 'MOTORCYCLE_UNIT' ? 'Unidad' : 'Descripción libre' }}
              </label>
              <SearchableSelect
                v-if="line.itemType === 'SPARE_PART'"
                v-model="line.sparePartId"
                :options="spareParts"
                :option-label="(p) => `${p.internalCode} · ${p.partCode} — ${p.description} (stock ${p.stock})`"
                placeholder="Escribe código interno, código de repuesto o nombre…"
                @change="onLineProductChange(line)"
              />
              <SearchableSelect
                v-else-if="line.itemType === 'MOTORCYCLE_UNIT'"
                v-model="line.motorcycleUnitId"
                :options="units"
                :option-label="unitLabel"
                placeholder="Escribe código, modelo, color, motor, serie o DUA…"
                @change="onLineProductChange(line)"
              />
              <input v-else v-model="line.description" class="form-input" placeholder="Escribe el ítem a cotizar (repuesto, servicio, etc.)" />
            </div>
            <div class="col-span-2">
              <label class="form-label text-xs">Cant.</label>
              <input v-model.number="line.quantity" type="number" min="1" class="form-input" :disabled="line.itemType === 'MOTORCYCLE_UNIT'" />
            </div>
            <div class="col-span-2">
              <label class="form-label flex items-center justify-between text-xs">
                <span>P. Unit.</span>
                <select v-model="line._usd" class="rounded border border-gray-200 px-0.5 text-[10px]">
                  <option :value="false">S/</option>
                  <option :value="true">US$</option>
                </select>
              </label>
              <div class="relative">
                <input v-model.number="line.unitPrice" type="number" step="0.01" min="0" class="form-input" />
                <p v-if="line._usd" class="absolute left-0 top-full mt-0.5 text-[10px] text-gray-400">= S/ {{ linePriceSoles(line).toFixed(2) }}</p>
              </div>
            </div>
            <div class="col-span-2">
              <label class="form-label text-xs">Dscto. (%)</label>
              <input
                v-model.number="line.discountPercent"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="0"
                class="form-input"
              />
            </div>
            <div class="col-span-1 flex items-center gap-1">
              <span class="w-16 truncate text-right text-xs font-medium text-gray-600">
                {{ lineNet(line).toFixed(2) }}
              </span>
              <button type="button" class="btn-secondary !px-2 !text-red-600" @click="lines.splice(i, 1)">✕</button>
            </div>
          </div>
          <div v-if="lines.length" class="mt-3 space-y-1 border-t border-gray-200 pt-3 text-right text-sm">
            <p class="text-xs text-gray-400">{{ form.igvExempt ? 'Operación exonerada de IGV (Amazonía)' : form.igvIncluded ? 'Precios con IGV incluido (zona local)' : 'IGV agregado al precio (venta al exterior)' }}</p>
            <p>Op. Gravada: <strong>S/ {{ subtotal.toFixed(2) }}</strong> · IGV (18%): <strong>S/ {{ igv.toFixed(2) }}</strong></p>
            <p class="text-base">Total a pagar: <strong>S/ {{ total.toFixed(2) }}</strong></p>
          </div>
        </div>

        <FormField label="Observaciones">
          <input v-model="form.notes" class="form-input" />
        </FormField>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Guardando…' : form.complete ? 'Registrar venta' : 'Guardar cotización' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Detalle / acciones -->
    <BaseModal :open="detail !== null" :title="`${detail?.saleNumber} — ${detail?.status}`" size="xl" @close="detail = null">
      <div v-if="detail" class="space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-2 text-gray-600">
          <p>Cliente: <strong class="text-gray-900">{{ detail.customerName }}</strong></p>
          <p>Documento: <strong class="text-gray-900">{{ detail.customerDocument }}</strong></p>
          <p>Vendedor: <strong class="text-gray-900">{{ detail.seller }}</strong></p>
          <p>Fecha: <strong class="text-gray-900">{{ detail.saleDate }}</strong></p>
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
        <p v-if="Number(detail.totalDiscount) > 0" class="text-right text-xs text-gray-500">
          Descuento total aplicado: −S/ {{ detail.totalDiscount }}
          <span v-if="detail.discountAuthorizedBy"> · autorizado por {{ detail.discountAuthorizedBy }}</span>
        </p>
        <p class="border-t border-gray-200 pt-2 text-right">
          Total: <strong>S/ {{ detail.total }}</strong> · Pagado: S/ {{ detail.paidAmount }} ·
          Saldo: <strong :class="Number(detail.balance) > 0 ? 'text-red-600' : 'text-green-700'">S/ {{ detail.balance }}</strong>
          <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ detail.paymentStatus }}</span>
        </p>

        <!-- Cobros -->
        <div v-if="detail.payments && detail.payments.length" class="rounded-lg bg-gray-50 p-3">
          <p class="mb-1 text-xs font-semibold uppercase text-gray-500">Cobros registrados</p>
          <p v-for="p in detail.payments" :key="p.id" class="text-xs text-gray-600">
            {{ new Date(p.createdAt).toLocaleString() }} — S/ {{ p.amount }} ({{ p.paymentMethodName }}) · {{ p.username }}
          </p>
        </div>

        <!-- Registrar cobro -->
        <div
          v-if="auth.can('sales.payments.create') && ['RESERVA', 'COMPLETADA'].includes(detail.status) && Number(detail.balance) > 0"
          class="rounded-lg border border-gray-200 p-3"
        >
          <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Registrar cobro (requiere caja abierta)</p>
          <div class="grid grid-cols-3 gap-2">
            <input v-model.number="payForm.amount" type="number" step="0.01" min="0.01" class="form-input" placeholder="Monto" />
            <select v-model.number="payForm.paymentMethodId" class="form-input">
              <option :value="null">Efectivo</option>
              <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <button type="button" class="btn-primary" @click="doPayment">Cobrar</button>
          </div>
        </div>

        <p v-if="payError" class="text-sm text-red-600">{{ payError }}</p>

        <!-- Acciones por estado -->
        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 pt-3">
          <button v-if="detail.status === 'COTIZACION'" class="btn-secondary" @click="printCotizacion(detail as any)">
            Imprimir cotización
          </button>
          <button
            v-if="auth.can('sales.list.edit') && detail.status === 'COTIZACION'"
            class="btn-primary"
            @click="doAction('complete')"
          >
            Aprobar
          </button>
          <button
            v-if="auth.can('sales.list.cancel') && detail.status !== 'ANULADA'"
            class="btn-secondary !text-red-600"
            @click="cancelTarget = detail"
          >
            {{ detail.status === 'COTIZACION' ? 'Rechazar' : 'Anular' }}
          </button>
        </div>
      </div>
    </BaseModal>

    <ConfirmDialog
      :open="cancelTarget !== null"
      :title="cancelTarget?.status === 'COTIZACION' ? 'Rechazar cotización' : 'Anular venta'"
      :message="cancelTarget?.status === 'COTIZACION'
        ? `La cotización ${cancelTarget?.saleNumber} se marcará como rechazada.`
        : `${cancelTarget?.saleNumber} se anulará: las unidades vuelven a DISPONIBLE y el stock vendido se revierte. No es posible si tiene cobros registrados. ¿Continuar?`"
      :confirm-label="cancelTarget?.status === 'COTIZACION' ? 'Rechazar' : 'Anular'"
      danger
      @confirm="confirmCancel"
      @cancel="cancelTarget = null"
    />

    <!-- Alta rápida de cliente (sin cerrar la venta) -->
    <BaseModal :open="customerModalOpen" title="Nuevo cliente" @close="customerModalOpen = false">
      <form class="space-y-4" @submit.prevent="saveNewCustomer">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Tipo de Documento" required>
            <select v-model="newCust.documentType" class="form-input" required>
              <option v-for="t in DOCUMENT_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </FormField>
          <FormField label="Número de Documento" required>
            <div class="flex gap-2">
              <input v-model="newCust.documentNumber" class="form-input" required maxlength="20" />
              <button
                type="button"
                class="btn-secondary whitespace-nowrap"
                :disabled="newCustLookingUp || !newCust.documentNumber"
                :title="`Consultar ${newCust.documentType} en línea`"
                @click="lookupNewCustomer"
              >
                {{ newCustLookingUp ? '…' : 'Buscar' }}
              </button>
            </div>
          </FormField>
        </div>
        <FormField :label="newCust.documentType === 'RUC' ? 'Razón Social' : 'Nombres y Apellidos'" required>
          <input v-model="newCust.name" class="form-input" required maxlength="200" />
        </FormField>

        <FormField label="Nombre Comercial">
          <input v-model="newCust.tradeName" class="form-input" maxlength="150" />
        </FormField>

        <FormField label="Dirección">
          <input v-model="newCust.address" class="form-input" maxlength="200" />
        </FormField>

        <UbigeoSelect
          v-model:department="newCust.department"
          v-model:province="newCust.province"
          v-model:district="newCust.district"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Teléfono">
            <input v-model="newCust.phone" class="form-input" maxlength="20" />
          </FormField>
          <FormField label="Correo">
            <input v-model="newCust.email" type="email" class="form-input" maxlength="150" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Tipo de cliente">
            <select v-model="newCust.customerTypeId" class="form-input">
              <option :value="null">Sin tipo (0% dcto.)</option>
              <option v-for="ct in customerTypes" :key="ct.id" :value="ct.id">
                {{ ct.name }}<template v-if="Number(ct.discountPercent) > 0"> — {{ Number(ct.discountPercent).toFixed(0) }}% dcto.</template>
              </option>
            </select>
          </FormField>
          <FormField label="Lista de precios">
            <select v-model="newCust.priceListId" class="form-input">
              <option :value="null">Predeterminada / precio base</option>
              <option v-for="pl in priceLists" :key="pl.id" :value="pl.id">
                {{ pl.name }}<template v-if="pl.isDefault"> (predeterminada)</template>
              </option>
            </select>
          </FormField>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="newCust.isActive" type="checkbox" /> Cliente activo
        </label>

        <p v-if="newCustError" class="text-sm text-red-600">{{ newCustError }}</p>
        <p class="text-xs text-gray-500">Se guardará en tu lista de clientes y quedará seleccionado en la venta.</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="customerModalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="newCustSaving">
            {{ newCustSaving ? 'Guardando…' : 'Crear y seleccionar' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </DefaultLayout>
</template>
