<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import { dispatchService } from '@/services/dispatch'
import { saleService } from '@/services/sales'
import { lookupService } from '@/services/lookup'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { DispatchGuideItem, DispatchItem } from '@/types/dispatch'
import { DISPATCH_MOTIVES } from '@/types/dispatch'
import type { SaleSummary } from '@/types/sales'

const toast = useToast()

const rows = ref<DispatchGuideItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const query = reactive({ page: 1, perPage: 15, search: '', status: '' })

const columns: TableColumn[] = [
  { key: 'fullNumber', label: 'Número' },
  { key: 'transferDate', label: 'Traslado' },
  { key: 'recipientName', label: 'Destinatario' },
  { key: 'motiveName', label: 'Motivo' },
  { key: 'status', label: 'Estado' },
]

async function load(): Promise<void> {
  loading.value = true
  try {
    const r = await dispatchService.list(query)
    rows.value = r.data
    meta.value = r.meta
  } finally {
    loading.value = false
  }
}

const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const detail = ref<DispatchGuideItem | null>(null)

const emptyItem = (): DispatchItem => ({ codigo: '', descripcion: '', cantidad: 1, unidad: 'NIU' })
const form = reactive({
  transferDate: new Date().toISOString().slice(0, 10),
  motive: '01',
  recipientDocType: 'DNI',
  recipientDocNumber: '',
  recipientName: '',
  originAddress: '',
  originUbigeo: '',
  destinationAddress: '',
  destinationUbigeo: '',
  transportMode: '02',
  carrierRuc: '',
  carrierName: '',
  vehiclePlate: '',
  driverLicense: '',
  driverName: '',
  totalWeight: 0,
  packages: 1,
  observations: '',
  saleId: null as number | null,
})
const items = ref<DispatchItem[]>([emptyItem()])

// Ventas completadas para generar la guía a partir del comprobante.
const sales = ref<SaleSummary[]>([])
const fromSaleId = ref<number | null>(null)
const lookupLoading = ref(false)

async function loadSales(): Promise<void> {
  try {
    const r = await saleService.list({ page: 1, perPage: 1000, search: '', sort: 'saleNumber', direction: 'desc', status: 'COMPLETADA' } as any)
    sales.value = r.data
  } catch {
    sales.value = []
  }
}

/** Carga destinatario e ítems desde una venta/comprobante ya hecho. */
async function loadFromSale(id: number | null): Promise<void> {
  fromSaleId.value = id
  if (!id) {
    form.saleId = null
    return
  }
  const s = await saleService.get(id)
  const parts = (s.customerDocument || '').split(' ')
  form.recipientDocType = parts[0] || 'DNI'
  form.recipientDocNumber = parts[1] || ''
  form.recipientName = s.customerName || ''
  form.saleId = id
  items.value = (s.items ?? []).map((i) => ({
    codigo: '',
    descripcion: (i.description || '').split('\n')[0],
    cantidad: i.quantity,
    unidad: 'NIU',
  }))
  if (items.value.length === 0) items.value = [emptyItem()]
}

/** Autocompleta el nombre del destinatario consultando DNI/RUC. */
async function lookupRecipient(): Promise<void> {
  const doc = form.recipientDocNumber.trim()
  if (!doc) return
  lookupLoading.value = true
  try {
    if (form.recipientDocType === 'RUC') {
      const c = await lookupService.ruc(doc)
      form.recipientName = c.razonSocial
    } else {
      const p = await lookupService.dni(doc)
      form.recipientName = p.nombreCompleto
    }
  } catch {
    toast.error('No se encontró ese documento.')
  } finally {
    lookupLoading.value = false
  }
}

function openCreate(): void {
  Object.assign(form, {
    transferDate: new Date().toISOString().slice(0, 10),
    motive: '01',
    recipientDocType: 'DNI',
    recipientDocNumber: '',
    recipientName: '',
    originAddress: '',
    originUbigeo: '',
    destinationAddress: '',
    destinationUbigeo: '',
    transportMode: '02',
    carrierRuc: '',
    carrierName: '',
    vehiclePlate: '',
    driverLicense: '',
    driverName: '',
    totalWeight: 0,
    packages: 1,
    observations: '',
    saleId: null,
  })
  items.value = [emptyItem()]
  fromSaleId.value = null
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    await dispatchService.create({ ...form, items: items.value })
    modalOpen.value = false
    toast.success('Guía de remisión creada.')
    await load()
  } catch (e: any) {
    formError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo crear la guía.'
  } finally {
    saving.value = false
  }
}

async function openDetail(row: DispatchGuideItem): Promise<void> {
  detail.value = await dispatchService.get(row.id)
}

onMounted(() => {
  load()
  loadSales()
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-lg font-bold text-gray-800">Guías de Remisión</h1>
      <button class="btn-primary" @click="openCreate">Nueva guía</button>
    </div>

    <div class="mb-3 flex gap-2">
      <input v-model="query.search" class="form-input max-w-xs" placeholder="Buscar por destinatario o número…" @keyup.enter="query.page = 1; load()" />
      <select v-model="query.status" class="form-input max-w-[12rem]" @change="query.page = 1; load()">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="ACEPTADO">Aceptado</option>
        <option value="RECHAZADO">Rechazado</option>
        <option value="ANULADO">Anulado</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="rows" :meta="meta" :loading="loading" @change="(p) => { query.page = p.page; load() }">
      <template #actions="{ row }">
        <button class="btn-secondary" @click="openDetail(row as unknown as DispatchGuideItem)">Ver</button>
      </template>
    </DataTable>

    <!-- Formulario -->
    <BaseModal :open="modalOpen" title="Nueva guía de remisión" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="rounded-lg border border-blue-100 bg-blue-50/40 p-3">
          <FormField label="Generar desde una venta/comprobante (autocompleta destinatario e ítems)">
            <SearchableSelect
              v-model="fromSaleId"
              :options="sales"
              :option-label="(s) => `${s.saleNumber} — ${s.customerName}`"
              placeholder="Busca la venta por número o cliente…"
              @change="loadFromSale(fromSaleId)"
            />
          </FormField>
          <p class="mt-1 text-xs text-gray-500">Elige la venta de la moto o repuestos ya realizada; el destinatario y los ítems se cargan solos. Igual puedes ajustarlos abajo.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <FormField label="Fecha de traslado" required>
            <input v-model="form.transferDate" type="date" class="form-input" required />
          </FormField>
          <FormField label="Motivo de traslado" required>
            <select v-model="form.motive" class="form-input">
              <option v-for="(name, code) in DISPATCH_MOTIVES" :key="code" :value="code">{{ name }}</option>
            </select>
          </FormField>
          <FormField label="Peso bruto total (KG)">
            <input v-model.number="form.totalWeight" type="number" step="0.001" min="0" class="form-input" />
          </FormField>
        </div>

        <div class="rounded-lg border border-gray-200 p-3">
          <p class="mb-2 text-sm font-medium text-gray-700">Destinatario</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <FormField label="Tipo doc.">
              <select v-model="form.recipientDocType" class="form-input">
                <option value="DNI">DNI</option>
                <option value="RUC">RUC</option>
                <option value="CE">C. Extranjería</option>
                <option value="OTRO">Otro</option>
              </select>
            </FormField>
            <FormField label="Número" required>
              <div class="flex gap-1">
                <input v-model="form.recipientDocNumber" class="form-input" required maxlength="20" />
                <button type="button" class="btn-secondary whitespace-nowrap" :disabled="lookupLoading" @click="lookupRecipient">{{ lookupLoading ? '…' : 'Buscar' }}</button>
              </div>
            </FormField>
            <FormField label="Nombre / Razón social" required>
              <input v-model="form.recipientName" class="form-input" required maxlength="200" />
            </FormField>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="rounded-lg border border-gray-200 p-3">
            <p class="mb-2 text-sm font-medium text-gray-700">Punto de partida</p>
            <FormField label="Dirección" required>
              <input v-model="form.originAddress" class="form-input" required maxlength="200" />
            </FormField>
            <FormField label="Ubigeo (6 dígitos)">
              <input v-model="form.originUbigeo" class="form-input" maxlength="6" placeholder="100601" />
            </FormField>
          </div>
          <div class="rounded-lg border border-gray-200 p-3">
            <p class="mb-2 text-sm font-medium text-gray-700">Punto de llegada</p>
            <FormField label="Dirección" required>
              <input v-model="form.destinationAddress" class="form-input" required maxlength="200" />
            </FormField>
            <FormField label="Ubigeo (6 dígitos)">
              <input v-model="form.destinationUbigeo" class="form-input" maxlength="6" />
            </FormField>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-3">
          <p class="mb-2 text-sm font-medium text-gray-700">Transporte</p>
          <FormField label="Modalidad">
            <select v-model="form.transportMode" class="form-input">
              <option value="02">Transporte privado (vehículo propio)</option>
              <option value="01">Transporte público (empresa transportista)</option>
            </select>
          </FormField>
          <div v-if="form.transportMode === '01'" class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField label="RUC del transportista">
              <input v-model="form.carrierRuc" class="form-input" maxlength="11" />
            </FormField>
            <FormField label="Razón social del transportista">
              <input v-model="form.carrierName" class="form-input" maxlength="200" />
            </FormField>
          </div>
          <div v-else class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <FormField label="Placa del vehículo">
              <input v-model="form.vehiclePlate" class="form-input" maxlength="20" placeholder="ABC-123" />
            </FormField>
            <FormField label="Licencia del conductor">
              <input v-model="form.driverLicense" class="form-input" maxlength="20" />
            </FormField>
            <FormField label="Nombre del conductor">
              <input v-model="form.driverName" class="form-input" maxlength="200" />
            </FormField>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-3">
          <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-700">Ítems a trasladar</p>
            <button type="button" class="btn-secondary" @click="items.push(emptyItem())">+ Ítem</button>
          </div>
          <div v-for="(it, i) in items" :key="i" class="mb-2 grid grid-cols-12 items-end gap-2">
            <div class="col-span-2"><label class="form-label text-xs">Código</label><input v-model="it.codigo" class="form-input" /></div>
            <div class="col-span-6"><label class="form-label text-xs">Descripción</label><input v-model="it.descripcion" class="form-input" /></div>
            <div class="col-span-2"><label class="form-label text-xs">Cant.</label><input v-model.number="it.cantidad" type="number" min="0" step="1" class="form-input" /></div>
            <div class="col-span-1"><label class="form-label text-xs">Und.</label><input v-model="it.unidad" class="form-input" /></div>
            <div class="col-span-1"><button type="button" class="btn-secondary !px-2 !text-red-600" @click="items.splice(i, 1)">✕</button></div>
          </div>
        </div>

        <FormField label="Observaciones">
          <input v-model="form.observations" class="form-input" />
        </FormField>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar guía' }}</button>
        </div>
      </form>
    </BaseModal>

    <!-- Detalle -->
    <BaseModal :open="detail !== null" :title="`Guía ${detail?.fullNumber}`" size="xl" @close="detail = null">
      <dl v-if="detail" class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        <div><dt class="text-xs uppercase text-gray-400">Estado</dt><dd class="font-medium">{{ detail.status }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Fecha de traslado</dt><dd>{{ detail.transferDate }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Motivo</dt><dd>{{ detail.motiveName }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Destinatario</dt><dd>{{ detail.recipientDocType }} {{ detail.recipientDocNumber }} — {{ detail.recipientName }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Partida</dt><dd>{{ detail.originAddress }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Llegada</dt><dd>{{ detail.destinationAddress }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Transporte</dt><dd>{{ detail.transportModeName }}{{ detail.vehiclePlate ? ' · ' + detail.vehiclePlate : '' }}{{ detail.carrierName ? ' · ' + detail.carrierName : '' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-400">Peso / Bultos</dt><dd>{{ detail.totalWeight }} {{ detail.weightUnit }} · {{ detail.packages }}</dd></div>
        <div class="sm:col-span-2">
          <dt class="text-xs uppercase text-gray-400">Ítems</dt>
          <dd><span v-for="(it, i) in detail.items" :key="i">{{ it.cantidad }}× {{ it.descripcion }}<span v-if="i < detail.items.length - 1"> | </span></span></dd>
        </div>
        <div v-if="detail.errorMessage" class="sm:col-span-2"><dt class="text-xs uppercase text-gray-400">Mensaje</dt><dd class="text-red-600">{{ detail.errorMessage }}</dd></div>
      </dl>
      <div class="mt-6 flex justify-end">
        <button class="btn-secondary" @click="detail = null">Cerrar</button>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
