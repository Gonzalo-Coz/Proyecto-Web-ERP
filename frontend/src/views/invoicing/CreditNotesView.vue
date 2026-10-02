<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import { invoicingService } from '@/services/invoicing'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { openComprobantePdf } from '@/utils/comprobante'
import type { PageMeta } from '@/types/common'
import type { InvoiceDocument } from '@/types/invoicing'

const auth = useAuthStore()
const toast = useToast()

const rows = ref<InvoiceDocument[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const search = ref('')
const statusFilter = ref('')
let debounce: ReturnType<typeof setTimeout> | undefined

const STATUS_COLORS: Record<string, string> = {
  PENDIENTE: 'bg-yellow-100 text-yellow-800',
  ACEPTADO: 'bg-green-100 text-green-800',
  RECHAZADO: 'bg-red-100 text-red-800',
  ANULADO: 'bg-gray-200 text-gray-700',
}

const detail = ref<InvoiceDocument | null>(null)

// Solo Notas de Crédito (tipo 07).
async function load(page = 1): Promise<void> {
  loading.value = true
  try {
    const result = await invoicingService.list(page, 10, search.value, statusFilter.value, '07')
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

async function openDetail(row: InvoiceDocument): Promise<void> {
  detail.value = await invoicingService.get(row.id)
}

async function downloadXml(id: number): Promise<void> {
  try {
    await invoicingService.downloadXml(id)
  } catch {
    toast.error('No se pudo descargar el XML.')
  }
}

async function doConsult(): Promise<void> {
  if (!detail.value) return
  try {
    detail.value = await invoicingService.consult(detail.value.id)
    await load()
    toast.success(`Estado sincronizado: ${detail.value.status}.`)
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo consultar en NubeFact.')
  }
}

async function doResend(): Promise<void> {
  if (!detail.value) return
  try {
    detail.value = await invoicingService.resend(detail.value.id)
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo reenviar.')
  }
}

/* ===== Importar una nota de crédito creada en el panel de NubeFact ===== */
const importModal = ref(false)
const importing = ref(false)
const importError = ref('')
const originalDocs = ref<InvoiceDocument[]>([])
const importForm = reactive({ originalDocumentId: null as number | null, series: '', correlative: null as number | null })

async function openImport(): Promise<void> {
  importForm.originalDocumentId = null
  importForm.series = ''
  importForm.correlative = null
  importError.value = ''
  importModal.value = true
  // Comprobantes aceptados (facturas/boletas) a los que puede referir la nota de crédito.
  try {
    const r = await invoicingService.list(1, 500, '', 'ACEPTADO', '')
    originalDocs.value = r.data.filter((d) => d.docType === '01' || d.docType === '03')
  } catch {
    originalDocs.value = []
  }
}

async function doImport(): Promise<void> {
  if (!importForm.originalDocumentId || !importForm.series.trim() || !importForm.correlative) {
    importError.value = 'Elige el comprobante original e indica la serie y el número de la nota de crédito.'
    return
  }
  importing.value = true
  importError.value = ''
  try {
    const nc = await invoicingService.importCreditNote(importForm.originalDocumentId, importForm.series.trim(), importForm.correlative)
    importModal.value = false
    detail.value = nc
    await load()
    toast.success('Nota de crédito importada de NubeFact.')
  } catch (e: any) {
    importError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo importar la nota de crédito.'
  } finally {
    importing.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <DefaultLayout>
    <div class="card p-0">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-2">
          <input v-model="search" type="search" class="form-input max-w-xs" placeholder="Buscar por número, cliente o venta…" @input="onSearch" />
          <select v-model="statusFilter" class="form-input w-48" @change="load(1)">
            <option value="">Todos los estados</option>
            <option value="PENDIENTE">Pendientes</option>
            <option value="ACEPTADO">Aceptadas por SUNAT</option>
            <option value="RECHAZADO">Rechazadas</option>
            <option value="ANULADO">Anuladas</option>
          </select>
        </div>
        <button v-if="auth.can('invoicing.documents.create')" class="btn-primary" @click="openImport">Importar de NubeFact</button>
      </div>
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3">Nota de crédito</th>
            <th class="px-4 py-3">Fecha</th>
            <th class="px-4 py-3">Cliente</th>
            <th class="px-4 py-3">Venta</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3">Estado SUNAT</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Cargando…</td></tr>
          <tr v-else-if="rows.length === 0"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin notas de crédito.</td></tr>
          <tr v-for="d in rows" v-else :key="d.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ d.docTypeName }} {{ d.fullNumber }}</td>
            <td class="px-4 py-3">{{ d.issueDate }}</td>
            <td class="px-4 py-3">{{ d.customerName }}</td>
            <td class="px-4 py-3 text-gray-500">{{ d.saleNumber }}</td>
            <td class="px-4 py-3 text-right">{{ d.currency === 'USD' ? 'US$' : 'S/' }} {{ d.total }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="STATUS_COLORS[d.status]">{{ d.status }}</span>
            </td>
            <td class="px-4 py-3 text-right"><button class="btn-secondary" @click="openDetail(d)">Ver</button></td>
          </tr>
        </tbody>
      </table>
      <div v-if="meta && meta.totalPages > 1" class="flex justify-end gap-2 border-t border-gray-200 p-3">
        <button class="btn-secondary" :disabled="meta.page <= 1" @click="load(meta.page - 1)">Anterior</button>
        <button class="btn-secondary" :disabled="meta.page >= meta.totalPages" @click="load(meta.page + 1)">Siguiente</button>
      </div>
    </div>

    <!-- Importar nota de crédito de NubeFact -->
    <BaseModal :open="importModal" title="Importar nota de crédito de NubeFact" @close="importModal = false">
      <div class="space-y-4">
        <p class="text-xs text-gray-500">
          Para notas de crédito que creaste directamente en el panel de NubeFact. Elige el comprobante original e indica la
          serie y el número de la nota; el sistema la consulta en NubeFact, confirma que existe y la registra aquí.
        </p>
        <FormField label="Comprobante original (factura o boleta)" required>
          <SearchableSelect
            v-model="importForm.originalDocumentId"
            :options="originalDocs"
            :option-label="(d) => `${d.docTypeName} ${d.fullNumber} — ${d.customerName}`"
            :option-search="(d) => `${d.saleNumber ?? ''} ${d.customerDocument ?? ''}`"
            placeholder="Busca por número o cliente…"
          />
        </FormField>
        <div class="grid grid-cols-2 gap-3">
          <FormField label="Serie de la NC" required>
            <input v-model="importForm.series" class="form-input" placeholder="Ej. FC01 / BC01" maxlength="10" />
          </FormField>
          <FormField label="Número de la NC" required>
            <input v-model.number="importForm.correlative" type="number" min="1" class="form-input" placeholder="Ej. 18" />
          </FormField>
        </div>
        <p v-if="importError" class="text-sm text-red-600">{{ importError }}</p>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="importModal = false">Cancelar</button>
          <button class="btn-primary" :disabled="importing" @click="doImport">{{ importing ? 'Consultando…' : 'Importar' }}</button>
        </div>
      </div>
    </BaseModal>

    <!-- Detalle -->
    <BaseModal :open="detail !== null" :title="`${detail?.docTypeName} ${detail?.fullNumber}`" @close="detail = null">
      <div v-if="detail" class="space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-2 text-gray-600">
          <p>Cliente: <strong class="text-gray-900">{{ detail.customerName }}</strong></p>
          <p>Documento: <strong class="text-gray-900">{{ detail.customerDocument }}</strong></p>
          <p>Fecha de emisión: <strong class="text-gray-900">{{ detail.issueDate }}</strong></p>
          <p>Venta origen: <strong class="text-gray-900">{{ detail.saleNumber }}</strong></p>
        </div>
        <p class="text-right">
          Subtotal: {{ detail.currency === 'USD' ? 'US$' : 'S/' }} {{ detail.subtotal }} · IGV: {{ detail.currency === 'USD' ? 'US$' : 'S/' }} {{ detail.igv }} · <strong>Total: {{ detail.currency === 'USD' ? 'US$' : 'S/' }} {{ detail.total }}</strong>
        </p>
        <div class="rounded-lg bg-gray-50 p-3 text-xs">
          <p><span class="font-semibold">Estado SUNAT:</span> {{ detail.status }}</p>
          <p v-if="detail.hash" class="break-all"><span class="font-semibold">Hash:</span> {{ detail.hash }}</p>
          <p v-if="detail.errorMessage" class="text-red-600"><span class="font-semibold">Error:</span> {{ detail.errorMessage }}</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 pt-3">
          <button class="btn-secondary" @click="openComprobantePdf(detail, 'a4')" title="Abre la nota de crédito A4 en PDF">PDF</button>
          <a v-if="detail.pdfUrl" class="btn-secondary" :href="detail.pdfUrl" target="_blank" rel="noopener" title="PDF original de NubeFact">PDF NubeFact</a>
          <button v-if="detail.xmlUrl" class="btn-secondary" @click="downloadXml(detail.id)">XML</button>
          <a v-if="detail.status === 'ACEPTADO' && detail.cdrUrl" class="btn-secondary" :href="detail.cdrUrl" target="_blank" rel="noopener" title="Constancia de recepción de SUNAT">CDR</a>
          <button v-if="auth.can('invoicing.documents.create') && detail.status !== 'ACEPTADO'" class="btn-secondary" title="Trae el estado real desde NubeFact" @click="doConsult">Consultar en NubeFact</button>
          <button v-if="auth.can('invoicing.documents.create') && detail.status !== 'ACEPTADO'" class="btn-primary" @click="doResend">Reenviar a SUNAT</button>
        </div>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
