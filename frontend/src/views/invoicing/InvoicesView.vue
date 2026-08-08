<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import { invoicingService } from '@/services/invoicing'
import { saleService } from '@/services/sales'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { printComprobante } from '@/utils/comprobante'
import type { PageMeta } from '@/types/common'
import type { InvoiceDocument } from '@/types/invoicing'
import type { SaleSummary } from '@/types/sales'

const auth = useAuthStore()

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
}

const issueModal = ref(false)
const issuableSales = ref<SaleSummary[]>([])
const issueForm = reactive({ saleId: 0, docType: '03' as '01' | '03' })

/**
 * Ventas que se pueden emitir según el tipo de comprobante:
 * - Factura (01): solo ventas con cliente RUC.
 * - Boleta (03): el resto (DNI, Público General, CE, etc.), no-RUC.
 */
const filteredSales = computed(() =>
  issuableSales.value.filter((s) =>
    issueForm.docType === '01' ? s.customerDocument.startsWith('RUC') : !s.customerDocument.startsWith('RUC'),
  ),
)

function onDocTypeChange(): void {
  issueForm.saleId = filteredSales.value[0]?.id ?? 0
}
const issueError = ref('')
const issuing = ref(false)

const detail = ref<InvoiceDocument | null>(null)
const toast = useToast()

async function downloadXml(id: number): Promise<void> {
  try {
    await invoicingService.downloadXml(id)
  } catch {
    toast.error('No se pudo descargar el XML.')
  }
}

async function load(page = 1): Promise<void> {
  loading.value = true
  try {
    const result = await invoicingService.list(page, 10, search.value, statusFilter.value)
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

async function openIssue(): Promise<void> {
  const sales = await saleService.list({ page: 1, perPage: 100, search: '', sort: 'saleDate', direction: 'desc', status: 'COMPLETADA' })
  issuableSales.value = sales.data
  issueForm.docType = '03'
  issueForm.saleId = filteredSales.value[0]?.id ?? 0
  issueError.value = ''
  issueModal.value = true
}

async function doIssue(): Promise<void> {
  issuing.value = true
  issueError.value = ''
  try {
    const doc = await invoicingService.issue(issueForm.saleId, issueForm.docType)
    issueModal.value = false
    detail.value = doc
    await load()
  } catch (e: any) {
    issueError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo emitir.'
  } finally {
    issuing.value = false
  }
}

async function openDetail(row: InvoiceDocument): Promise<void> {
  detail.value = await invoicingService.get(row.id)
}

async function doResend(): Promise<void> {
  if (!detail.value) return
  detail.value = await invoicingService.resend(detail.value.id)
  await load()
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
            <option value="ACEPTADO">Aceptados por SUNAT</option>
            <option value="RECHAZADO">Rechazados</option>
          </select>
        </div>
        <button v-if="auth.can('invoicing.documents.create')" class="btn-primary" @click="openIssue">
          Emitir comprobante
        </button>
      </div>
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3">Comprobante</th>
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
          <tr v-else-if="rows.length === 0"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin comprobantes.</td></tr>
          <tr v-for="d in rows" v-else :key="d.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ d.docTypeName }} {{ d.fullNumber }}</td>
            <td class="px-4 py-3">{{ d.issueDate }}</td>
            <td class="px-4 py-3">{{ d.customerName }}</td>
            <td class="px-4 py-3 text-gray-500">{{ d.saleNumber }}</td>
            <td class="px-4 py-3 text-right">S/ {{ d.total }}</td>
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

    <!-- Emitir -->
    <BaseModal :open="issueModal" title="Emitir comprobante electrónico" @close="issueModal = false">
      <div class="space-y-4">
        <FormField label="Tipo de comprobante" required>
          <select v-model="issueForm.docType" class="form-input" @change="onDocTypeChange">
            <option value="03">Boleta Electrónica</option>
            <option value="01">Factura Electrónica (requiere RUC)</option>
          </select>
        </FormField>
        <FormField label="Venta completada" required>
          <select v-model.number="issueForm.saleId" class="form-input">
            <option v-for="s in filteredSales" :key="s.id" :value="s.id">
              {{ s.saleNumber }} — {{ s.customerName }} (S/ {{ s.total }})
            </option>
          </select>
          <p v-if="filteredSales.length === 0" class="mt-1 text-xs text-amber-600">
            {{ issueForm.docType === '01'
              ? 'No hay ventas con cliente RUC para emitir factura.'
              : 'No hay ventas para boleta (todas son de clientes con RUC).' }}
          </p>
        </FormField>
        <p class="text-xs text-gray-500">
          El comprobante se enviará a <strong>NubeFact</strong> (según el ambiente configurado). Obtendrá su estado real
          y, si SUNAT lo acepta, los enlaces oficiales de PDF / XML / CDR.
        </p>
        <p v-if="issueError" class="text-sm text-red-600">{{ issueError }}</p>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="issueModal = false">Cancelar</button>
          <button class="btn-primary" :disabled="issuing || filteredSales.length === 0" @click="doIssue">
            {{ issuing ? 'Emitiendo…' : 'Emitir' }}
          </button>
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
          Subtotal: S/ {{ detail.subtotal }} · IGV: S/ {{ detail.igv }} · <strong>Total: S/ {{ detail.total }}</strong>
        </p>
        <div class="rounded-lg bg-gray-50 p-3 text-xs">
          <p><span class="font-semibold">Estado SUNAT:</span> {{ detail.status }}</p>
          <p v-if="detail.hash" class="break-all"><span class="font-semibold">Hash:</span> {{ detail.hash }}</p>
          <p v-if="detail.qrData" class="break-all"><span class="font-semibold">QR:</span> {{ detail.qrData }}</p>
          <p v-if="detail.cdr" class="break-all"><span class="font-semibold">CDR:</span> {{ detail.cdr }}</p>
          <p v-if="detail.errorMessage" class="text-red-600"><span class="font-semibold">Error:</span> {{ detail.errorMessage }}</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 pt-3">
          <button class="btn-secondary" @click="printComprobante(detail, 'ticket')" title="Ticket para impresora de tira">Ticket</button>
          <button class="btn-secondary" @click="printComprobante(detail, 'a4')" title="Documento A4">PDF</button>
          <!-- El oficial de NubeFact (válido, con QR real): solo cuando SUNAT lo acepta. -->
          <a
            v-if="detail.status === 'ACEPTADO' && detail.pdfUrl"
            class="btn-primary"
            :href="detail.pdfUrl"
            target="_blank"
            rel="noopener"
            title="PDF oficial validado por SUNAT (con QR real)"
          >
            PDF oficial
          </a>
          <!-- XML oficial: solo descargable si SUNAT lo aceptó (válido). -->
          <a
            v-if="detail.status === 'ACEPTADO' && detail.xmlUrl"
            class="btn-secondary"
            :href="detail.xmlUrl"
            target="_blank"
            rel="noopener"
          >
            XML
          </a>
          <button
            v-else
            class="btn-secondary cursor-not-allowed opacity-50"
            disabled
            title="El XML solo se puede descargar cuando SUNAT acepta el comprobante"
          >
            XML
          </button>
          <a
            v-if="detail.status === 'ACEPTADO' && detail.cdrUrl"
            class="btn-secondary"
            :href="detail.cdrUrl"
            target="_blank"
            rel="noopener"
            title="Constancia de recepción de SUNAT"
          >
            CDR
          </a>
          <button
            v-if="auth.can('invoicing.documents.create') && detail.status !== 'ACEPTADO'"
            class="btn-secondary"
            title="Trae el estado real y los enlaces desde NubeFact"
            @click="doConsult"
          >
            Consultar en NubeFact
          </button>
          <button
            v-if="auth.can('invoicing.documents.create') && detail.status !== 'ACEPTADO'"
            class="btn-primary"
            @click="doResend"
          >
            Reenviar a SUNAT
          </button>
        </div>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
