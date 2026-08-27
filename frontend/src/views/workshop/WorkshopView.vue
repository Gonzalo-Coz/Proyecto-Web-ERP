<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import { workshopService } from '@/services/workshop'
import { maintenanceService } from '@/services/maintenance'
import { printServiceOrder } from '@/utils/serviceOrder'
import { customerService } from '@/services/masters'
import { unitService } from '@/services/motorcycles'
import { sparePartService } from '@/services/inventory'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta } from '@/types/common'
import type { CustomerItem } from '@/types/masters'
import type { UnitItem } from '@/types/motorcycles'
import type { SparePartItem } from '@/types/inventory'
import { ORDER_STATUSES, type OrderStatus, type ServiceOrderItem, type ServiceOrderSummary } from '@/types/workshop'
import type { MaintenancePlanActivity, MaintenancePlanModel, MaintenancePlanServiceDetail } from '@/types/maintenance'

const auth = useAuthStore()

const STATUS_COLORS: Record<OrderStatus, string> = {
  RECIBIDA: 'bg-gray-200 text-gray-700',
  EN_DIAGNOSTICO: 'bg-blue-100 text-blue-800',
  ESPERANDO_REPUESTOS: 'bg-orange-100 text-orange-800',
  EN_REPARACION: 'bg-yellow-100 text-yellow-800',
  LISTA_PARA_ENTREGA: 'bg-teal-100 text-teal-800',
  ENTREGADA: 'bg-green-100 text-green-800',
  GARANTIA: 'bg-purple-100 text-purple-800',
  ANULADA: 'bg-red-100 text-red-700',
}

const rows = ref<ServiceOrderSummary[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const search = ref('')
const statusFilter = ref('')
let debounce: ReturnType<typeof setTimeout> | undefined

const customers = ref<CustomerItem[]>([])
const units = ref<UnitItem[]>([])
const spareParts = ref<SparePartItem[]>([])

const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  customerId: 0,
  broughtBy: null as string | null,
  motorcycleUnitId: null as number | null,
  motorcycleDescription: null as string | null,
  plate: null as string | null,
  mileage: null as number | null,
  entryDate: new Date().toISOString().slice(0, 10),
  estimatedDate: null as string | null,
  mechanicName: null as string | null,
  diagnosis: null as string | null,
  notes: null as string | null,
})

const detail = ref<ServiceOrderSummary | null>(null)
const detailError = ref('')
// Alta múltiple: varias filas de trabajo/repuesto a la vez ("más casillas").
interface DraftItem {
  itemType: 'PART' | 'LABOR'
  sparePartId: number | null
  description: string
  quantity: number
  unitPrice: number
}
function blankDraft(): DraftItem {
  return { itemType: 'LABOR', sparePartId: spareParts.value[0]?.id ?? null, description: '', quantity: 1, unitPrice: 0 }
}
const draftItems = ref<DraftItem[]>([blankDraft()])
const newStatus = ref<OrderStatus>('RECIBIDA')

// --- Plan de mantenimiento por kilometraje ---
const planModels = ref<MaintenancePlanModel[]>([])
const planForm = reactive({ planId: null as number | null, km: null as number | null })
const planPreview = ref<MaintenancePlanServiceDetail | null>(null)
const planWarnings = ref<string[]>([])
const planLoading = ref(false)
const planApplied = ref(false)
// Selección de repuestos del kit a cargar (por código). Los no disponibles no se pueden marcar.
const planPartSel = reactive<Record<string, boolean>>({})

/** Un repuesto está disponible si está en inventario y tiene stock suficiente. */
function partAvailable(p: { inInventory: boolean; stock: number | null; quantity: number }): boolean {
  return p.inInventory && (p.stock ?? 0) >= p.quantity
}

const selectedPlanModel = computed(() => planModels.value.find((m) => m.id === planForm.planId) ?? null)

// Ítems separados: mantenimiento programado (del plan) vs adicionales (manuales).
const planItems = computed<ServiceOrderItem[]>(() => detail.value?.items?.filter((i) => i.fromPlan) ?? [])
const extraItems = computed<ServiceOrderItem[]>(() => detail.value?.items?.filter((i) => !i.fromPlan) ?? [])
function sumItems(list: ServiceOrderItem[]): number {
  return list.reduce((a, i) => a + Number(i.lineTotal), 0)
}

/** Etiqueta y color de cada acción de la leyenda (I/R/A/E/L). */
const ACTION_LABEL: Record<string, string> = {
  I: 'Inspeccionar', R: 'Reemplazar', A: 'Ajustar', E: 'Engrasar / Lubricar', L: 'Limpiar',
}
const ACTION_COLOR: Record<string, string> = {
  I: 'bg-blue-100 text-blue-700', R: 'bg-red-100 text-red-700', A: 'bg-amber-100 text-amber-700',
  E: 'bg-emerald-100 text-emerald-700', L: 'bg-purple-100 text-purple-700',
}

/** Actividades de la rutina agrupadas por sistema (para la checklist). */
const planActivityGroups = computed(() => {
  const groups: { system: string; items: MaintenancePlanActivity[] }[] = []
  for (const a of planPreview.value?.activities ?? []) {
    let g = groups.find((x) => x.system === a.system)
    if (!g) {
      g = { system: a.system, items: [] }
      groups.push(g)
    }
    g.items.push(a)
  }
  return groups
})

function onPlanModelChange(): void {
  planPreview.value = null
  planApplied.value = false
  planForm.km = selectedPlanModel.value?.kmIntervals[0] ?? null
}

async function loadPlanPreview(): Promise<void> {
  planPreview.value = null
  planApplied.value = false
  const planId = planForm.planId
  const km = planForm.km
  if (!planId || !km) return
  planLoading.value = true
  try {
    const svc = await maintenanceService.service(planId, km)
    planPreview.value = svc
    // Marca por defecto los repuestos disponibles; los que faltan quedan sin marcar.
    Object.keys(planPartSel).forEach((k) => delete planPartSel[k])
    for (const p of svc.parts) planPartSel[p.code] = partAvailable(p)
  } catch {
    detailError.value = 'No se pudo cargar el plan.'
  } finally {
    planLoading.value = false
  }
}

async function applyPlan(): Promise<void> {
  const orderId = detail.value?.id
  const planId = planForm.planId
  const km = planForm.km
  if (!orderId || !planId || !km) return
  detailError.value = ''
  planWarnings.value = []
  planLoading.value = true
  try {
    // Solo se cargan los repuestos disponibles que el mecánico dejó marcados.
    const selectedIds = (planPreview.value?.parts ?? [])
      .filter((p) => partAvailable(p) && planPartSel[p.code] && p.sparePartId != null)
      .map((p) => p.sparePartId as number)
    const res = await workshopService.applyPlan(orderId, planId, km, selectedIds)
    planWarnings.value = res.planWarnings ?? []
    detail.value = res
    planApplied.value = true // se mantiene la checklist visible como guía
    await load(meta.value?.page ?? 1)
  } catch (e: any) {
    detailError.value = e.response?.data?.detail ?? 'No se pudo cargar el plan a la orden.'
  } finally {
    planLoading.value = false
  }
}

async function load(page = 1): Promise<void> {
  loading.value = true
  try {
    const result = await workshopService.list(page, 10, search.value, statusFilter.value)
    rows.value = result.data
    meta.value = result.meta
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  clearTimeout(debounce)
  debounce = setTimeout(() => load(1), 300)
}

function openCreate(): void {
  Object.assign(form, {
    customerId: customers.value[0]?.id ?? 0,
    broughtBy: null,
    motorcycleUnitId: null,
    motorcycleDescription: null,
    plate: null,
    mileage: null,
    entryDate: new Date().toISOString().slice(0, 10),
    estimatedDate: null,
    mechanicName: null,
    diagnosis: null,
    notes: null,
  })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const created = await workshopService.create({ ...form })
    modalOpen.value = false
    detail.value = created
    newStatus.value = created.status
    await load()
  } catch (e: any) {
    formError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo crear la orden.'
  } finally {
    saving.value = false
  }
}

async function openDetail(row: ServiceOrderSummary): Promise<void> {
  detail.value = await workshopService.get(row.id)
  newStatus.value = detail.value.status
  detailError.value = ''
  draftItems.value = [blankDraft()]
  // Reinicia el cargador de plan de mantenimiento para esta orden.
  planForm.planId = null
  planForm.km = null
  planPreview.value = null
  planWarnings.value = []
  planApplied.value = false
  Object.keys(planPartSel).forEach((k) => delete planPartSel[k])
}

function addDraftRow(): void {
  draftItems.value.push(blankDraft())
}
function removeDraftRow(i: number): void {
  draftItems.value.splice(i, 1)
  if (!draftItems.value.length) draftItems.value.push(blankDraft())
}

/** Agrega TODAS las filas completadas a la orden (una llamada por fila). */
async function submitDrafts(): Promise<void> {
  if (!detail.value) return
  detailError.value = ''
  const orderId = detail.value.id
  const rows = draftItems.value.filter((d) => (d.itemType === 'PART' ? d.sparePartId != null : d.description.trim() !== ''))
  if (!rows.length) {
    detailError.value = 'Completa al menos una fila (descripción o repuesto).'
    return
  }
  try {
    for (const d of rows) {
      detail.value = await workshopService.addItem(orderId, {
        itemType: d.itemType,
        sparePartId: d.sparePartId,
        description: d.description,
        quantity: d.quantity,
        unitPrice: d.unitPrice,
      })
    }
    draftItems.value = [blankDraft()]
    await load(meta.value?.page ?? 1)
  } catch (e: any) {
    detailError.value = e.response?.data?.detail ?? 'No se pudo agregar alguna línea.'
  }
}

async function doRemoveItem(itemId: number): Promise<void> {
  if (!detail.value) return
  try {
    detail.value = await workshopService.removeItem(detail.value.id, itemId)
    await load(meta.value?.page ?? 1)
  } catch (e: any) {
    detailError.value = e.response?.data?.detail ?? 'No se pudo retirar la línea.'
  }
}

async function doChangeStatus(): Promise<void> {
  if (!detail.value) return
  detailError.value = ''
  try {
    detail.value = await workshopService.changeStatus(detail.value.id, newStatus.value)
    await load(meta.value?.page ?? 1)
  } catch (e: any) {
    detailError.value = e.response?.data?.detail ?? 'No se pudo cambiar el estado.'
  }
}

async function doInvoice(): Promise<void> {
  if (!detail.value) return
  detailError.value = ''
  try {
    detail.value = await workshopService.invoice(detail.value.id)
    await load(meta.value?.page ?? 1)
  } catch (e: any) {
    detailError.value = e.response?.data?.detail ?? 'No se pudo facturar la orden.'
  }
}

async function doCancel(): Promise<void> {
  if (!detail.value) return
  const reason = window.prompt('Motivo de anulación (opcional):') ?? ''
  if (!window.confirm('¿Anular esta orden? Se devolverá el stock de los repuestos usados.')) return
  detailError.value = ''
  try {
    detail.value = await workshopService.cancel(detail.value.id, reason)
    await load(meta.value?.page ?? 1)
  } catch (e: any) {
    detailError.value = e.response?.data?.detail ?? 'No se pudo anular la orden.'
  }
}

/** La orden se puede editar si no está entregada ni anulada. */
const orderEditable = computed(() => detail.value != null && !detail.value.deliveredAt && detail.value.status !== 'ANULADA')

function doPrint(): void {
  if (detail.value) printServiceOrder(detail.value)
}

onMounted(async () => {
  // Los datos del formulario se cargan PRIMERO e independientes: si la lista de
  // órdenes falla, el formulario de recepción igual tiene clientes/motos/repuestos.
  try {
    customers.value = (await customerService.list({ page: 1, perPage: 100, search: '', sort: 'name', direction: 'asc' })).data.filter((c) => c.isActive)
  } catch { /* no bloquear el formulario */ }
  try {
    units.value = (await unitService.list({ page: 1, perPage: 100, search: '', sort: 'internalCode', direction: 'asc' })).data.filter((u) => u.status !== 'BAJA')
  } catch { units.value = [] }
  try {
    spareParts.value = (await sparePartService.list({ page: 1, perPage: 100, search: '', sort: 'description', direction: 'asc' })).data.filter((p) => p.isActive)
  } catch { spareParts.value = [] }
  try {
    planModels.value = await maintenanceService.models()
  } catch { planModels.value = [] }
  load().catch(() => undefined)
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-4 flex flex-wrap gap-2">
      <button
        v-for="f in ['', ...ORDER_STATUSES]"
        :key="f"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
        :class="statusFilter === f ? 'bg-primary-600 text-white' : 'border border-gray-300 bg-white text-gray-700'"
        @click="statusFilter = f; load(1)"
      >
        {{ f === '' ? 'Todas' : f.replaceAll('_', ' ') }}
      </button>
    </div>

    <div class="card p-0">
      <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4">
        <input v-model="search" type="search" class="form-input max-w-xs" placeholder="Buscar por orden, cliente o placa…" @input="onSearch" />
        <button v-if="auth.can('workshop.orders.create')" class="btn-primary" @click="openCreate">
          Recepcionar motocicleta
        </button>
      </div>
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3">Orden</th>
            <th class="px-4 py-3">Cliente</th>
            <th class="px-4 py-3">Motocicleta</th>
            <th class="px-4 py-3">Mecánico</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando…</td></tr>
          <tr v-else-if="rows.length === 0"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin órdenes.</td></tr>
          <tr v-for="o in rows" v-else :key="o.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ o.orderNumber }}</td>
            <td class="px-4 py-3">{{ o.customerName }}</td>
            <td class="px-4 py-3 text-gray-600">{{ o.motorcycleLabel }} <span v-if="o.plate" class="text-xs text-gray-400">({{ o.plate }})</span></td>
            <td class="px-4 py-3 text-gray-500">{{ o.mechanicName ?? '—' }}</td>
            <td class="px-4 py-3 text-right">S/ {{ o.total }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="STATUS_COLORS[o.status]">
                {{ o.status.replaceAll('_', ' ') }}
              </span>
            </td>
            <td class="px-4 py-3 text-right"><button class="btn-secondary" @click="openDetail(o)">Ver</button></td>
          </tr>
        </tbody>
      </table>
      <div v-if="meta && meta.totalPages > 1" class="flex justify-end gap-2 border-t border-gray-200 p-3">
        <button class="btn-secondary" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Anterior</button>
        <button class="btn-secondary" :disabled="meta.page >= meta.totalPages" @click="load(meta.page + 1)">Siguiente</button>
      </div>
    </div>

    <!-- Recepción -->
    <BaseModal :open="modalOpen" title="Recepción de motocicleta" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Cliente (titular)" required>
            <select v-model.number="form.customerId" class="form-input" required>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.documentNumber }})</option>
            </select>
          </FormField>
          <FormField label="Ingresa / a nombre de (opcional)">
            <input v-model="form.broughtBy" class="form-input" maxlength="150" placeholder="Quién trae la moto, si es distinto" />
          </FormField>
        </div>
        <FormField label="Unidad vendida por la empresa (expediente digital)">
          <select v-model.number="form.motorcycleUnitId" class="form-input">
            <option :value="null">— Motocicleta externa —</option>
            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.internalCode }} — {{ u.modelName }} ({{ u.vin }})</option>
          </select>
        </FormField>
        <div v-if="form.motorcycleUnitId === null" class="grid grid-cols-3 gap-4">
          <div class="col-span-2">
            <FormField label="Descripción de la motocicleta" required>
              <input v-model="form.motorcycleDescription" class="form-input" required maxlength="200" placeholder="Honda CB190R 2022 roja" />
            </FormField>
          </div>
          <FormField label="Placa">
            <input v-model="form.plate" class="form-input uppercase" maxlength="10" />
          </FormField>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <FormField label="Kilometraje">
            <input v-model.number="form.mileage" type="number" min="0" class="form-input" />
          </FormField>
          <FormField label="Fecha de ingreso" required>
            <input v-model="form.entryDate" type="date" class="form-input" required />
          </FormField>
          <FormField label="Fecha estimada">
            <input v-model="form.estimatedDate" type="date" class="form-input" />
          </FormField>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Mecánico asignado">
            <input v-model="form.mechanicName" class="form-input" maxlength="100" />
          </FormField>
          <FormField label="Diagnóstico inicial">
            <input v-model="form.diagnosis" class="form-input" />
          </FormField>
        </div>
        <FormField label="Observaciones">
          <input v-model="form.notes" class="form-input" />
        </FormField>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Crear orden' }}</button>
        </div>
      </form>
    </BaseModal>

    <!-- Detalle -->
    <BaseModal :open="detail !== null" :title="`${detail?.orderNumber} — ${detail?.customerName}`" size="xl" @close="detail = null">
      <div v-if="detail" class="space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-2 text-gray-600">
          <p>Cliente (titular): <strong class="text-gray-900">{{ detail.customerName }}</strong> <span v-if="detail.customerDocument" class="text-xs text-gray-400">({{ detail.customerDocument }})</span></p>
          <p v-if="detail.broughtBy">Ingresa / a nombre de: <strong class="text-gray-900">{{ detail.broughtBy }}</strong></p>
          <p v-if="detail.planModel" class="col-span-2 rounded bg-primary-50 px-2 py-1">
            Plan de mantenimiento: <strong class="text-primary-700">{{ detail.planModel }} — {{ detail.planKm?.toLocaleString('es-PE') }} km</strong>
          </p>
          <p>Motocicleta: <strong class="text-gray-900">{{ detail.motorcycleLabel }}</strong></p>
          <p>Placa: <strong class="text-gray-900">{{ detail.plate ?? '—' }}</strong></p>
          <p>Kilometraje: <strong class="text-gray-900">{{ detail.mileage ?? '—' }}</strong></p>
          <p>Ingreso: <strong class="text-gray-900">{{ detail.entryDate }}</strong></p>
          <p>Estimada: <strong class="text-gray-900">{{ detail.estimatedDate ?? '—' }}</strong></p>
          <p>Mecánico: <strong class="text-gray-900">{{ detail.mechanicName ?? '—' }}</strong></p>
          <p class="col-span-2">Diagnóstico: <strong class="text-gray-900">{{ detail.diagnosis ?? '—' }}</strong></p>
          <p v-if="detail.notes" class="col-span-2">Observaciones: <strong class="text-gray-900">{{ detail.notes }}</strong></p>
        </div>

        <div class="flex justify-end">
          <button class="btn-secondary" @click="doPrint">🖨 Imprimir Orden de Servicio</button>
        </div>

        <!-- Estado -->
        <div v-if="auth.can('workshop.orders.edit') && orderEditable" class="flex items-end gap-2 rounded-lg bg-gray-50 p-3">
          <div class="flex-1">
            <label class="form-label text-xs">Estado de la orden</label>
            <select v-model="newStatus" class="form-input">
              <option v-for="s in ORDER_STATUSES" :key="s" :value="s">{{ s.replaceAll('_', ' ') }}</option>
            </select>
          </div>
          <button class="btn-primary" @click="doChangeStatus">Actualizar</button>
        </div>

        <!-- Plan de mantenimiento por kilometraje -->
        <div v-if="auth.can('workshop.orders.edit') && orderEditable" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
          <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Cargar plan de mantenimiento</p>
          <div class="grid grid-cols-12 items-end gap-2">
            <div class="col-span-5">
              <label class="form-label text-xs">Modelo</label>
              <select v-model.number="planForm.planId" class="form-input" @change="onPlanModelChange">
                <option :value="null">— Selecciona —</option>
                <option v-for="m in planModels" :key="m.id" :value="m.id">{{ m.model }}</option>
              </select>
            </div>
            <div class="col-span-4">
              <label class="form-label text-xs">Kilometraje</label>
              <select v-model.number="planForm.km" class="form-input" :disabled="!selectedPlanModel" @change="loadPlanPreview">
                <option :value="null">— km —</option>
                <option v-for="k in selectedPlanModel?.kmIntervals ?? []" :key="k" :value="k">{{ k.toLocaleString('es-PE') }} km</option>
              </select>
            </div>
            <div class="col-span-3">
              <button class="btn-secondary w-full" :disabled="!planForm.planId || !planForm.km || planLoading" @click="loadPlanPreview">
                Ver plan
              </button>
            </div>
          </div>

          <!-- Vista previa del servicio -->
          <div v-if="planPreview" class="mt-3 space-y-2">
            <p class="text-sm text-gray-700">
              Mano de obra:
              <strong :class="planPreview.labor?.free ? 'text-emerald-600' : ''">
                {{ planPreview.labor?.free ? 'Gratuito (mantenimiento incluido)' : planPreview.labor?.cost != null ? `S/ ${planPreview.labor.cost.toFixed(2)}` : 'definir manualmente' }}
              </strong>
              <span v-if="planPreview.labor?.hours != null" class="text-xs text-gray-400"> · {{ planPreview.labor.hours }} h</span>
              <span class="text-xs text-gray-400"> · {{ planPreview.activities.length }} actividades de revisión</span>
            </p>

            <!-- Checklist de la rutina: qué se hace en cada punto según la leyenda -->
            <div v-if="planActivityGroups.length" class="rounded-lg border border-gray-200 bg-white p-3">
              <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase text-gray-500">Rutina del servicio ({{ planPreview.km.toLocaleString('es-PE') }} km)</p>
                <div class="flex flex-wrap gap-1">
                  <span v-for="(lab, k) in ACTION_LABEL" :key="k" class="inline-flex items-center gap-1 text-[10px] text-gray-500">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded font-bold" :class="ACTION_COLOR[k]">{{ k }}</span>{{ lab }}
                  </span>
                </div>
              </div>
              <div v-for="g in planActivityGroups" :key="g.system" class="border-t border-gray-100 py-1.5 first:border-t-0">
                <p class="text-xs font-semibold text-gray-600">{{ g.system }}</p>
                <ul class="mt-0.5 space-y-0.5">
                  <li v-for="(a, idx) in g.items" :key="idx" class="flex items-start gap-2 text-xs text-gray-700">
                    <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded font-bold" :class="ACTION_COLOR[a.action] ?? 'bg-gray-100 text-gray-600'" :title="ACTION_LABEL[a.action] ?? a.action">{{ a.action }}</span>
                    <span>{{ a.activity }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <table class="w-full text-left text-xs">
              <thead class="uppercase text-gray-500">
                <tr>
                  <th class="w-6 py-1"></th>
                  <th class="py-1">Repuesto</th>
                  <th class="py-1 text-right">Cant.</th>
                  <th class="py-1 text-right">P.Unit</th>
                  <th class="py-1 text-right">Stock</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in planPreview.parts" :key="p.code" class="border-t border-gray-100" :class="partAvailable(p) ? '' : 'text-red-600'">
                  <td class="py-1">
                    <input v-model="planPartSel[p.code]" type="checkbox" :disabled="!partAvailable(p) || planApplied" />
                  </td>
                  <td class="py-1">
                    <span class="mr-1 inline-flex h-4 w-4 items-center justify-center rounded font-bold" :class="ACTION_COLOR[p.action ?? 'R']" :title="ACTION_LABEL[p.action ?? 'R']">{{ p.action ?? 'R' }}</span>
                    {{ p.description }} <span :class="partAvailable(p) ? 'text-gray-400' : 'text-red-400'">({{ p.code }})</span>
                  </td>
                  <td class="py-1 text-right">{{ p.quantity }}</td>
                  <td class="py-1 text-right">{{ p.inInventory ? `S/ ${Number(p.salePrice ?? 0).toFixed(2)}` : '—' }}</td>
                  <td class="py-1 text-right font-medium">
                    {{ !p.inInventory ? 'sin ficha' : (p.stock ?? 0) < p.quantity ? `stock ${p.stock}` : p.stock }}
                  </td>
                </tr>
              </tbody>
            </table>
            <p class="text-[11px] text-gray-400">
              <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded bg-red-100 text-[9px] font-bold text-red-700">R</span>
              Reemplazar / Cambiar. En rojo: sin stock o no cargados en inventario (no se cargan). Puedes destildar los que no uses, y agregar o retirar repuestos en la orden después de cargar.
            </p>
            <div class="flex justify-end">
              <span v-if="planApplied" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                ✓ Cargado a la orden
              </span>
              <button v-else class="btn-primary" :disabled="planLoading" @click="applyPlan">Cargar a la orden</button>
            </div>
          </div>

          <!-- Avisos tras cargar el plan -->
          <div v-if="planWarnings.length" class="mt-2 rounded border border-orange-200 bg-orange-50 p-2 text-xs text-orange-700">
            <p class="font-semibold">Repuestos no cargados automáticamente (agrégalos manualmente):</p>
            <ul class="ml-4 list-disc">
              <li v-for="(w, i) in planWarnings" :key="i">{{ w }}</li>
            </ul>
          </div>
        </div>

        <!-- Items agrupados: mantenimiento programado vs adicionales -->
        <p v-if="!detail.items?.length" class="py-3 text-center text-sm text-gray-400">Sin trabajos registrados.</p>

        <div v-if="planItems.length" class="mb-3">
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-primary-700">Mantenimiento programado</p>
          <table class="w-full text-left">
            <thead class="text-xs uppercase text-gray-500">
              <tr><th class="py-1">Tipo</th><th class="py-1">Descripción</th><th class="py-1 text-right">Cant.</th><th class="py-1 text-right">P.Unit</th><th class="py-1 text-right">Total</th><th /></tr>
            </thead>
            <tbody>
              <tr v-for="i in planItems" :key="i.id" class="border-t border-gray-100">
                <td class="py-1 text-xs">{{ i.itemType === 'PART' ? 'REPUESTO' : 'MANO DE OBRA' }}</td>
                <td class="py-1">{{ i.description }}</td>
                <td class="py-1 text-right">{{ i.quantity }}</td>
                <td class="py-1 text-right">{{ i.unitPrice }}</td>
                <td class="py-1 text-right">{{ i.lineTotal }}</td>
                <td class="py-1 text-right">
                  <button v-if="auth.can('workshop.orders.edit') && orderEditable" class="text-xs text-red-600 hover:underline" @click="doRemoveItem(i.id)">Retirar</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="text-right text-xs text-gray-500">Subtotal programado: <strong class="text-gray-700">S/ {{ sumItems(planItems).toFixed(2) }}</strong></p>
        </div>

        <div v-if="extraItems.length" class="mb-3">
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-700">Adicionales (repuestos y/o trabajos)</p>
          <table class="w-full text-left">
            <thead class="text-xs uppercase text-gray-500">
              <tr><th class="py-1">Tipo</th><th class="py-1">Descripción</th><th class="py-1 text-right">Cant.</th><th class="py-1 text-right">P.Unit</th><th class="py-1 text-right">Total</th><th /></tr>
            </thead>
            <tbody>
              <tr v-for="i in extraItems" :key="i.id" class="border-t border-gray-100">
                <td class="py-1 text-xs">{{ i.itemType === 'PART' ? 'REPUESTO' : 'MANO DE OBRA' }}</td>
                <td class="py-1">{{ i.description }}</td>
                <td class="py-1 text-right">{{ i.quantity }}</td>
                <td class="py-1 text-right">{{ i.unitPrice }}</td>
                <td class="py-1 text-right">{{ i.lineTotal }}</td>
                <td class="py-1 text-right">
                  <button v-if="auth.can('workshop.orders.edit') && orderEditable" class="text-xs text-red-600 hover:underline" @click="doRemoveItem(i.id)">Retirar</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="text-right text-xs text-gray-500">Subtotal adicionales: <strong class="text-gray-700">S/ {{ sumItems(extraItems).toFixed(2) }}</strong></p>
        </div>

        <p class="border-t border-gray-200 pt-2 text-right text-base">Total de la orden: <strong>S/ {{ detail.total }}</strong></p>

        <!-- Agregar item -->
        <div v-if="auth.can('workshop.orders.edit') && orderEditable" class="rounded-lg border border-gray-200 p-3">
          <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Agregar trabajo o repuesto</p>
          <div v-for="(d, i) in draftItems" :key="i" class="mb-2 grid grid-cols-12 items-end gap-2">
            <div class="col-span-2">
              <label v-if="i === 0" class="form-label text-xs">Tipo</label>
              <select v-model="d.itemType" class="form-input">
                <option value="LABOR">Mano de obra</option>
                <option value="PART">Repuesto</option>
              </select>
            </div>
            <div class="col-span-5">
              <label v-if="i === 0" class="form-label text-xs">{{ d.itemType === 'PART' ? 'Repuesto (descuenta stock)' : 'Descripción' }}</label>
              <select v-if="d.itemType === 'PART'" v-model.number="d.sparePartId" class="form-input">
                <option v-for="p in spareParts" :key="p.id" :value="p.id">{{ p.internalCode }} · {{ p.partCode }} — {{ p.description }} (stock {{ p.stock }})</option>
              </select>
              <input v-else v-model="d.description" class="form-input" placeholder="Cambio de aceite y filtro" />
            </div>
            <div class="col-span-2">
              <label v-if="i === 0" class="form-label text-xs">Cant.</label>
              <input v-model.number="d.quantity" type="number" min="1" class="form-input" />
            </div>
            <div class="col-span-2">
              <label v-if="i === 0" class="form-label text-xs">P. Unit.</label>
              <input v-model.number="d.unitPrice" type="number" step="0.01" min="0" class="form-input" />
            </div>
            <div class="col-span-1">
              <button class="btn-secondary !px-2 !text-red-600" title="Quitar fila" @click="removeDraftRow(i)">✕</button>
            </div>
          </div>
          <div class="mt-1 flex items-center justify-between">
            <button class="text-xs font-medium text-primary-600 hover:underline" @click="addDraftRow">+ agregar otra fila</button>
            <button class="btn-primary" @click="submitDrafts">Agregar a la orden</button>
          </div>
        </div>

        <p v-if="detailError" class="text-sm text-red-600">{{ detailError }}</p>

        <div class="flex items-center justify-between gap-2 border-t border-gray-200 pt-3">
          <button
            v-if="auth.can('workshop.orders.edit') && orderEditable && !detail.invoiceSaleId"
            class="text-sm font-medium text-red-600 hover:underline"
            @click="doCancel"
          >
            Anular orden
          </button>
          <span v-else />
          <div class="flex items-center gap-2">
            <span v-if="detail.status === 'ANULADA'" class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
              Orden anulada
            </span>
            <span v-if="detail.invoiceSaleId" class="rounded-full bg-green-100 px-3 py-1 text-xs text-green-800">
              Facturada (venta #{{ detail.invoiceSaleId }})
            </span>
            <button
              v-if="auth.can('workshop.orders.approve') && !detail.invoiceSaleId && orderEditable && (detail.items?.length ?? 0) > 0"
              class="btn-primary"
              @click="doInvoice"
            >
              Facturar orden
            </button>
          </div>
        </div>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
