<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import { paymentService } from '@/services/payments'
import { customerService } from '@/services/masters'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { CustomerItem } from '@/types/masters'
import { PAYMENT_METHODS, PAYMENT_STATUSES, type PaymentTransactionItem } from '@/types/payments'

const auth = useAuthStore()
const toast = useToast()

const columns: TableColumn[] = [
  { key: 'createdAt', label: 'Fecha' },
  { key: 'method', label: 'Medio' },
  { key: 'amount', label: 'Monto' },
  { key: 'operationNumber', label: 'N° operación' },
  { key: 'saleNumber', label: 'Venta' },
  { key: 'status', label: 'Estado' },
]

const rows = ref<PaymentTransactionItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const statusFilter = ref('')
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'createdAt', direction: 'desc' as 'asc' | 'desc' })

const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const emptyForm = {
  method: 'YAPE',
  amount: null as number | null,
  operationNumber: '' as string,
  saleNumber: '' as string,
  customerLabel: '' as string,
  notes: '' as string,
}
const form = reactive({ ...emptyForm })

const methods = PAYMENT_METHODS
const statuses = PAYMENT_STATUSES
const customers = ref<CustomerItem[]>([])

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await paymentService.list({ ...query, status: statusFilter.value })
    rows.value = result.data
    meta.value = result.meta
  } finally {
    loading.value = false
  }
}

function onTableChange(p: { page: number; search: string; sort: string; direction: 'asc' | 'desc' }): void {
  query.page = p.page
  query.search = p.search
  if (p.sort) {
    query.sort = p.sort
    query.direction = p.direction
  }
  load()
}

function openRegister(): void {
  Object.assign(form, emptyForm)
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    await paymentService.register({
      method: form.method,
      amount: Number(form.amount),
      operationNumber: form.operationNumber || null,
      saleId: null,
      saleNumber: form.saleNumber || null,
      customerLabel: form.customerLabel || null,
      notes: form.notes || null,
    })
    modalOpen.value = false
    await load()
    toast.success('Pago registrado. Queda pendiente de validación.')
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo registrar la transacción.'
  } finally {
    saving.value = false
  }
}

async function approve(t: PaymentTransactionItem): Promise<void> {
  try {
    await paymentService.approve(t.id)
    await load()
    toast.success('Pago aprobado.')
  } catch {
    toast.error('No se pudo aprobar el pago.')
  }
}
async function reject(t: PaymentTransactionItem): Promise<void> {
  try {
    await paymentService.reject(t.id)
    await load()
    toast.success('Pago rechazado.')
  } catch {
    toast.error('No se pudo rechazar el pago.')
  }
}

const money = (v: string): string => `S/ ${Number(v).toFixed(2)}`
const fmtDate = (v: string | null): string =>
  v ? new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(v)) : '—'
const statusClass = (s: string): string =>
  ({
    PENDING: 'bg-amber-50 text-amber-700',
    APPROVED: 'bg-emerald-50 text-emerald-700',
    REJECTED: 'bg-red-50 text-red-600',
    VOIDED: 'bg-slate-100 text-slate-500',
  })[s] ?? 'bg-slate-100 text-slate-500'
const statusLabel = (s: string): string =>
  ({ PENDING: 'Pendiente', APPROVED: 'Aprobada', REJECTED: 'Rechazada', VOIDED: 'Anulada' })[s] ?? s

onMounted(async () => {
  await load()
  try {
    customers.value = (await customerService.list({ page: 1, perPage: 100, search: '', sort: 'name', direction: 'asc' })).data.filter((c) => c.isActive)
  } catch {
    customers.value = []
  }
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-6 flex items-end justify-between">
      <div>
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Pasarela de Pago</h2>
        <p class="text-sm text-slate-500">Registro de pagos digitales (Yape, Plin, tarjeta, transferencia) y su validación manual.</p>
      </div>
      <button v-if="auth.can('payments.gateway.create')" class="btn-primary" @click="openRegister">Registrar pago</button>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows as unknown as Record<string, unknown>[]"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por operación, venta o cliente…"
      @change="onTableChange"
    >
      <template #toolbar>
        <select v-model="statusFilter" class="form-input w-44" @change="load">
          <option value="">Todos los estados</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
        </select>
      </template>

      <template #cell-createdAt="{ row }">
        <span class="whitespace-nowrap text-slate-600">{{ fmtDate((row as PaymentTransactionItem).createdAt) }}</span>
      </template>
      <template #cell-amount="{ row }">
        <span class="font-semibold tabular-nums">{{ money((row as PaymentTransactionItem).amount) }}</span>
      </template>
      <template #cell-status="{ row }">
        <span class="chip" :class="statusClass((row as PaymentTransactionItem).status)">
          {{ statusLabel((row as PaymentTransactionItem).status) }}
        </span>
      </template>
      <template #actions="{ row }">
        <template v-if="(row as PaymentTransactionItem).status === 'PENDING' && auth.can('payments.gateway.validate')">
          <button class="btn-secondary" @click="approve(row as unknown as PaymentTransactionItem)">Aprobar</button>
          <button class="btn-danger ml-2" @click="reject(row as unknown as PaymentTransactionItem)">Rechazar</button>
        </template>
        <span v-else class="text-xs text-slate-400">—</span>
      </template>
    </DataTable>

    <BaseModal :open="modalOpen" title="Registrar pago" size="lg" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Medio de pago" required>
            <select v-model="form.method" class="form-input">
              <option v-for="m in methods" :key="m" :value="m">{{ m }}</option>
            </select>
          </FormField>
          <FormField label="Monto (S/)" required>
            <input v-model.number="form.amount" type="number" step="0.01" min="0" class="form-input" required />
          </FormField>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="N° de operación">
            <input v-model="form.operationNumber" class="form-input" maxlength="60" placeholder="Del voucher / app" />
          </FormField>
          <FormField label="N° de venta (opcional)">
            <input v-model="form.saleNumber" class="form-input" maxlength="30" />
          </FormField>
        </div>
        <FormField label="Cliente (opcional)">
          <select v-model="form.customerLabel" class="form-input">
            <option value="">— Ninguno —</option>
            <option value="Público General">Público General</option>
            <option v-for="c in customers" :key="c.id" :value="c.name">{{ c.name }} ({{ c.documentNumber }})</option>
          </select>
        </FormField>
        <FormField label="Notas">
          <input v-model="form.notes" class="form-input" maxlength="255" />
        </FormField>
        <p class="text-xs text-slate-400">
          La transacción queda <strong>pendiente</strong> hasta validarla contra el comprobante. La integración con una
          pasarela real se conectará cambiando solo el adaptador, sin afectar este registro.
        </p>
        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Registrando…' : 'Registrar' }}</button>
        </div>
      </form>
    </BaseModal>
  </DefaultLayout>
</template>
