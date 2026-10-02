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
import { mediaUrl } from '@/services/company'
import api from '@/services/api'
import { openDispatchGuidePdf, type GuideCompany } from '@/utils/dispatchGuide'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { DispatchGuideItem, DispatchItem, SaleDocumentOption } from '@/types/dispatch'
import { DISPATCH_MOTIVES } from '@/types/dispatch'

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
const editingId = ref<number | null>(null)
const carrierLookupLoading = ref(false)

// Datos que se recuerdan para próximas guías (punto de partida y transportista
// habituales). Se guardan en el navegador y se precargan al crear una nueva guía.
const DEFAULTS_KEY = 'yigm.dispatch.defaults'
type GuideDefaults = {
  originAddress?: string
  originUbigeo?: string
  transportMode?: string
  carrierRuc?: string
  carrierName?: string
  vehiclePlate?: string
  driverLicense?: string
  driverName?: string
}
function loadDefaults(): GuideDefaults {
  try {
    return JSON.parse(localStorage.getItem(DEFAULTS_KEY) || '{}') as GuideDefaults
  } catch {
    return {}
  }
}
function saveDefaults(): void {
  try {
    const d: GuideDefaults = {
      originAddress: form.originAddress,
      originUbigeo: form.originUbigeo,
      transportMode: form.transportMode,
      carrierRuc: form.carrierRuc,
      carrierName: form.carrierName,
      vehiclePlate: form.vehiclePlate,
      driverLicense: form.driverLicense,
      driverName: form.driverName,
    }
    localStorage.setItem(DEFAULTS_KEY, JSON.stringify(d))
  } catch {
    /* localStorage no disponible: se ignora */
  }
}

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
  relatedDocType: '',
  relatedDocNumber: '',
})
const items = ref<DispatchItem[]>([emptyItem()])

// Comprobantes de venta aceptados para generar la guía. Se puede filtrar por tipo
// (Factura/Boleta) y buscar escribiendo por número o cliente.
const saleDocs = ref<SaleDocumentOption[]>([])
const docTypeFilter = ref<string>('') // '' = todos, '01' factura, '03' boleta
const fromSaleId = ref<number | null>(null)
const lookupLoading = ref(false)

async function loadSaleDocuments(): Promise<void> {
  try {
    saleDocs.value = await dispatchService.saleDocuments(docTypeFilter.value, '')
  } catch {
    saleDocs.value = []
  }
}

/** Carga los ítems y el comprobante relacionado desde la venta elegida. */
async function loadFromSaleDocument(saleId: number | null): Promise<void> {
  fromSaleId.value = saleId
  if (!saleId) {
    form.saleId = null
    form.relatedDocType = ''
    form.relatedDocNumber = ''
    return
  }
  const opt = saleDocs.value.find((d) => d.saleId === saleId)
  if (opt) {
    form.relatedDocType = opt.docType
    form.relatedDocNumber = opt.fullNumber
  }
  form.saleId = saleId
  // NO se autocompleta el destinatario: al trasladar, el destinatario suele ser
  // distinto al cliente de la venta. Solo se jalan los ítems.
  const s = await saleService.get(saleId)
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

/** Autocompleta la razón social del transportista consultando su RUC. */
async function lookupCarrier(): Promise<void> {
  const ruc = form.carrierRuc.trim()
  if (!ruc) return
  carrierLookupLoading.value = true
  try {
    const c = await lookupService.ruc(ruc)
    form.carrierName = c.razonSocial
  } catch {
    toast.error('No se encontró ese RUC de transportista.')
  } finally {
    carrierLookupLoading.value = false
  }
}

function openCreate(): void {
  const d = loadDefaults()
  Object.assign(form, {
    transferDate: new Date().toISOString().slice(0, 10),
    motive: '01',
    recipientDocType: 'DNI',
    recipientDocNumber: '',
    recipientName: '',
    originAddress: d.originAddress ?? '',
    originUbigeo: d.originUbigeo ?? '',
    destinationAddress: '',
    destinationUbigeo: '',
    transportMode: d.transportMode ?? '02',
    carrierRuc: d.carrierRuc ?? '',
    carrierName: d.carrierName ?? '',
    vehiclePlate: d.vehiclePlate ?? '',
    driverLicense: d.driverLicense ?? '',
    driverName: d.driverName ?? '',
    totalWeight: 0,
    packages: 1,
    observations: '',
    saleId: null,
    relatedDocType: '',
    relatedDocNumber: '',
  })
  items.value = [emptyItem()]
  fromSaleId.value = null
  editingId.value = null
  formError.value = ''
  modalOpen.value = true
}

/** Abre el formulario para editar una guía existente (no ACEPTADA). */
function openEdit(g: DispatchGuideItem): void {
  Object.assign(form, {
    transferDate: g.transferDate,
    motive: g.motive,
    recipientDocType: g.recipientDocType,
    recipientDocNumber: g.recipientDocNumber,
    recipientName: g.recipientName,
    originAddress: g.originAddress,
    originUbigeo: g.originUbigeo ?? '',
    destinationAddress: g.destinationAddress,
    destinationUbigeo: g.destinationUbigeo ?? '',
    transportMode: g.transportMode,
    carrierRuc: g.carrierRuc ?? '',
    carrierName: g.carrierName ?? '',
    vehiclePlate: g.vehiclePlate ?? '',
    driverLicense: g.driverLicense ?? '',
    driverName: g.driverName ?? '',
    totalWeight: Number(g.totalWeight) || 0,
    packages: g.packages ?? 1,
    observations: g.observations ?? '',
    saleId: g.saleId ?? null,
    relatedDocType: g.relatedDocType ?? '',
    relatedDocNumber: g.relatedDocNumber ?? '',
  })
  items.value = (g.items ?? []).map((i) => ({
    codigo: i.codigo ?? '',
    descripcion: i.descripcion ?? '',
    cantidad: i.cantidad ?? 1,
    unidad: i.unidad ?? 'NIU',
  }))
  if (items.value.length === 0) items.value = [emptyItem()]
  fromSaleId.value = null
  editingId.value = g.id
  formError.value = ''
  detail.value = null
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form, items: items.value }
    if (editingId.value !== null) {
      await dispatchService.update(editingId.value, payload)
      toast.success('Guía de remisión actualizada.')
    } else {
      await dispatchService.create(payload)
      toast.success('Guía de remisión creada.')
    }
    saveDefaults()
    modalOpen.value = false
    await load()
  } catch (e: any) {
    formError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo guardar la guía.'
  } finally {
    saving.value = false
  }
}

const annulling = ref(false)
async function doAnnul(): Promise<void> {
  if (!detail.value) return
  const reason = window.prompt('Motivo de la anulación (opcional):', '') ?? ''
  if (!window.confirm('¿Anular esta guía de remisión? Esta acción refleja la baja hecha en SUNAT.')) return
  annulling.value = true
  try {
    detail.value = await dispatchService.annul(detail.value.id, reason)
    await load()
    toast.success('Guía anulada.')
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo anular la guía.')
  } finally {
    annulling.value = false
  }
}

async function openDetail(row: DispatchGuideItem): Promise<void> {
  detail.value = await dispatchService.get(row.id)
}

// Datos de la empresa para el encabezado del documento (mismo estilo que los comprobantes).
const company = ref<GuideCompany | null>(null)
async function loadCompany(): Promise<void> {
  try {
    const { data } = await api.get('/settings')
    const s = data.data as Record<string, string>
    const abs = (p: string) => (p && !p.startsWith('http') ? window.location.origin + (p.startsWith('/') ? p : '/' + p) : p)
    const logoPath = mediaUrl(s['company.logo_full_path'])
    company.value = {
      name: s['company.name'] ?? '',
      tradeName: s['company.trade_name'] ?? null,
      ruc: s['company.ruc'] ?? '',
      address: s['company.address'] ?? null,
      department: s['company.department'] ?? null,
      province: s['company.province'] ?? null,
      district: s['company.district'] ?? null,
      phone: s['company.phone'] ?? null,
      email: s['company.email'] ?? null,
      logo: logoPath ? abs(logoPath) : abs('/brand/logo-full.png'),
    }
  } catch {
    company.value = null
  }
}

async function doPrint(): Promise<void> {
  if (!detail.value) return
  const co = company.value ?? { name: '', ruc: '', logo: window.location.origin + '/brand/logo-full.png' }
  await openDispatchGuidePdf(detail.value, co)
}

const emitting = ref(false)
async function doEmit(): Promise<void> {
  if (!detail.value) return
  emitting.value = true
  try {
    detail.value = await dispatchService.emit(detail.value.id)
    await load()
    toast.success(detail.value.status === 'ACEPTADO' ? 'Guía ACEPTADA por SUNAT.' : `Guía enviada (${detail.value.status}).`)
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo emitir la guía.')
  } finally {
    emitting.value = false
  }
}
async function doConsult(): Promise<void> {
  if (!detail.value) return
  try {
    detail.value = await dispatchService.consult(detail.value.id)
    await load()
    toast.success(`Estado: ${detail.value.status}.`)
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? 'No se pudo consultar la guía.')
  }
}

onMounted(() => {
  load()
  loadSaleDocuments()
  loadCompany()
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
    <BaseModal :open="modalOpen" :title="editingId !== null ? 'Editar guía de remisión' : 'Nueva guía de remisión'" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="rounded-lg border border-blue-100 bg-blue-50/40 p-3">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-[10rem,1fr]">
            <FormField label="Tipo de comprobante">
              <select v-model="docTypeFilter" class="form-input" @change="loadSaleDocuments(); fromSaleId = null">
                <option value="">Todos</option>
                <option value="01">Factura</option>
                <option value="03">Boleta</option>
              </select>
            </FormField>
            <FormField label="Comprobante de venta (carga los ítems)">
              <SearchableSelect
                v-model="fromSaleId"
                :options="saleDocs"
                value-key="saleId"
                :option-label="(d) => `${d.docTypeName} ${d.fullNumber} — ${d.customerName}`"
                :option-search="(d) => `${d.saleNumber} ${d.issueDate}`"
                placeholder="Escribe el número de comprobante o el cliente…"
                @change="loadFromSaleDocument(fromSaleId)"
              />
            </FormField>
          </div>
          <p v-if="form.relatedDocNumber" class="mt-1 text-xs text-blue-700">Documento relacionado: <b>{{ form.relatedDocType === '01' ? 'Factura' : form.relatedDocType === '03' ? 'Boleta' : 'Comprobante' }} {{ form.relatedDocNumber }}</b></p>
          <p class="mt-1 text-xs text-gray-500">Elige el comprobante de la venta ya emitida (aceptado por SUNAT); se cargan los ítems y se guarda como documento relacionado. El destinatario se ingresa aparte, pues suele ser distinto al cliente de la venta.</p>
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
              <div class="flex gap-1">
                <input v-model="form.carrierRuc" class="form-input" maxlength="11" />
                <button type="button" class="btn-secondary whitespace-nowrap" :disabled="carrierLookupLoading" @click="lookupCarrier">{{ carrierLookupLoading ? '…' : 'Buscar' }}</button>
              </div>
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
        <div v-if="detail.relatedDocNumber"><dt class="text-xs uppercase text-gray-400">Comprobante relacionado</dt><dd>{{ detail.relatedDocTypeName }} {{ detail.relatedDocNumber }}</dd></div>
        <div class="sm:col-span-2">
          <dt class="text-xs uppercase text-gray-400">Ítems</dt>
          <dd><span v-for="(it, i) in detail.items" :key="i">{{ it.cantidad }}× {{ it.descripcion }}<span v-if="i < detail.items.length - 1"> | </span></span></dd>
        </div>
        <div v-if="detail.errorMessage" class="sm:col-span-2"><dt class="text-xs uppercase text-gray-400">Mensaje</dt><dd class="text-red-600">{{ detail.errorMessage }}</dd></div>
      </dl>
      <div class="mt-6 flex flex-wrap justify-end gap-2">
        <button v-if="detail" class="btn-secondary" @click="doPrint">Imprimir / PDF</button>
        <a v-if="detail?.pdfUrl" :href="detail.pdfUrl" target="_blank" rel="noopener" class="btn-secondary">PDF SUNAT</a>
        <button v-if="detail && detail.status !== 'ACEPTADO' && detail.status !== 'ANULADO'" class="btn-secondary" @click="openEdit(detail)">Editar</button>
        <button v-if="detail && detail.status !== 'ANULADO'" class="btn-secondary !text-red-600" :disabled="annulling" @click="doAnnul">{{ annulling ? 'Anulando…' : 'Anular' }}</button>
        <button v-if="detail && detail.status !== 'ACEPTADO'" class="btn-secondary" @click="doConsult">Consultar</button>
        <button v-if="detail && detail.status !== 'ACEPTADO'" class="btn-primary" :disabled="emitting" @click="doEmit">{{ emitting ? 'Emitiendo…' : 'Emitir a SUNAT' }}</button>
        <button class="btn-secondary" @click="detail = null">Cerrar</button>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
