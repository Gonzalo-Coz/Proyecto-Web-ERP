<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import { cashService } from '@/services/cash'
import { catalogService } from '@/services/catalogs'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta } from '@/types/common'
import type { CashMovementItem, CashSessionItem } from '@/types/cash'
import type { CatalogItem } from '@/types/catalogs'

const auth = useAuthStore()

const current = ref<CashSessionItem | null>(null)
const currentMovements = ref<CashMovementItem[]>([])
const history = ref<CashSessionItem[]>([])
const historyMeta = ref<PageMeta | null>(null)
const paymentMethods = ref<CatalogItem[]>([])
const errorMsg = ref('')

const openModal = ref(false)
const openForm = reactive({ openingAmount: 0, notes: '' })

const closeModal = ref(false)
const closeForm = reactive({ countedAmount: 0, notes: '' })

const movModal = ref(false)
const movForm = reactive({
  movementType: 'INGRESO' as 'INGRESO' | 'EGRESO',
  amount: 0,
  paymentMethodId: null as number | null,
  concept: '',
})

const detailSession = ref<CashSessionItem | null>(null)
const detailMovements = ref<CashMovementItem[]>([])

async function refresh(page = 1): Promise<void> {
  errorMsg.value = ''
  const result = await cashService.current()
  current.value = result.session
  if (current.value) {
    currentMovements.value = await cashService.movements(current.value.id)
  } else {
    currentMovements.value = []
  }
  const h = await cashService.sessions(page)
  history.value = h.data
  historyMeta.value = h.meta
}

async function doOpen(): Promise<void> {
  try {
    await cashService.open(openForm.openingAmount, openForm.notes || null)
    openModal.value = false
    await refresh()
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo abrir la caja.'
    openModal.value = false
  }
}

async function doClose(): Promise<void> {
  if (!current.value) return
  try {
    await cashService.close(current.value.id, closeForm.countedAmount, closeForm.notes || null)
    closeModal.value = false
    await refresh()
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo cerrar la caja.'
    closeModal.value = false
  }
}

async function doMovement(): Promise<void> {
  try {
    await cashService.addMovement(movForm.movementType, movForm.amount, movForm.paymentMethodId, movForm.concept)
    movModal.value = false
    await refresh()
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo registrar el movimiento.'
    movModal.value = false
  }
}

async function openDetail(session: CashSessionItem): Promise<void> {
  detailSession.value = session
  detailMovements.value = await cashService.movements(session.id)
}

onMounted(async () => {
  await refresh()
  paymentMethods.value = (await catalogService.list('payment_methods')).filter((m) => m.isActive)
})
</script>

<template>
  <DefaultLayout>
    <p v-if="errorMsg" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ errorMsg }}</p>

    <!-- Estado actual -->
    <div class="card mb-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">
            {{ current ? `Caja ${current.sessionNumber} — ABIERTA` : 'Caja cerrada' }}
          </h2>
          <p v-if="current" class="text-sm text-gray-500">
            Abierta por {{ current.openedBy }} · {{ new Date(current.openedAt).toLocaleString() }}
          </p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="!current && auth.can('cash.sessions.create')"
            class="btn-primary"
            @click="openForm.openingAmount = 0; openForm.notes = ''; openModal = true"
          >
            Abrir caja
          </button>
          <template v-if="current">
            <button
              v-if="auth.can('cash.movements.create')"
              class="btn-secondary"
              @click="movForm.amount = 0; movForm.concept = ''; movModal = true"
            >
              Registrar movimiento
            </button>
            <button
              v-if="auth.can('cash.sessions.edit')"
              class="btn-primary"
              @click="closeForm.countedAmount = Number(current.liveExpectedCash); closeForm.notes = ''; closeModal = true"
            >
              Cerrar caja
            </button>
          </template>
        </div>
      </div>

      <div v-if="current" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg bg-gray-50 p-3">
          <p class="text-xs text-gray-500">Apertura</p>
          <p class="text-lg font-semibold">S/ {{ current.openingAmount }}</p>
        </div>
        <div class="rounded-lg bg-green-50 p-3">
          <p class="text-xs text-gray-500">Ingresos</p>
          <p class="text-lg font-semibold text-green-700">S/ {{ current.totalIncome }}</p>
        </div>
        <div class="rounded-lg bg-red-50 p-3">
          <p class="text-xs text-gray-500">Egresos</p>
          <p class="text-lg font-semibold text-red-700">S/ {{ current.totalExpense }}</p>
        </div>
        <div class="rounded-lg bg-primary-50 p-3">
          <p class="text-xs text-gray-500">Efectivo esperado</p>
          <p class="text-lg font-semibold text-primary-700">S/ {{ current.liveExpectedCash }}</p>
        </div>
      </div>

      <!-- Movimientos de la sesión abierta -->
      <div v-if="current" class="mt-4">
        <h3 class="mb-2 text-sm font-medium text-gray-700">Movimientos de la sesión</h3>
        <table class="w-full text-left text-sm">
          <thead class="text-xs uppercase text-gray-500">
            <tr><th class="py-1">Hora</th><th class="py-1">Tipo</th><th class="py-1">Concepto</th><th class="py-1">Medio</th><th class="py-1 text-right">Monto</th><th class="py-1">Usuario</th></tr>
          </thead>
          <tbody>
            <tr v-if="currentMovements.length === 0"><td colspan="6" class="py-4 text-center text-gray-400">Sin movimientos.</td></tr>
            <tr v-for="m in currentMovements" :key="m.id" class="border-t border-gray-100">
              <td class="py-1 text-xs">{{ new Date(m.createdAt).toLocaleTimeString() }}</td>
              <td class="py-1" :class="m.movementType === 'INGRESO' ? 'text-green-700' : 'text-red-700'">{{ m.movementType }}</td>
              <td class="py-1">{{ m.concept }}</td>
              <td class="py-1 text-gray-500">{{ m.paymentMethodName }}</td>
              <td class="py-1 text-right font-medium">S/ {{ m.amount }}</td>
              <td class="py-1 text-xs text-gray-500">{{ m.username }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Historial -->
    <div class="card p-0">
      <h3 class="border-b border-gray-200 p-4 text-sm font-medium text-gray-700">Historial de sesiones</h3>
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
          <tr><th class="px-4 py-2">Número</th><th class="px-4 py-2">Apertura</th><th class="px-4 py-2">Cierre</th><th class="px-4 py-2 text-right">Esperado</th><th class="px-4 py-2 text-right">Contado</th><th class="px-4 py-2 text-right">Diferencia</th><th class="px-4 py-2 text-right">Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-for="s in history" :key="s.id" class="border-t border-gray-100">
            <td class="px-4 py-2">{{ s.sessionNumber }} <span v-if="s.status === 'ABIERTA'" class="ml-1 rounded-full bg-green-100 px-2 text-xs text-green-800">abierta</span></td>
            <td class="px-4 py-2 text-xs">{{ new Date(s.openedAt).toLocaleString() }}</td>
            <td class="px-4 py-2 text-xs">{{ s.closedAt ? new Date(s.closedAt).toLocaleString() : '—' }}</td>
            <td class="px-4 py-2 text-right">{{ s.expectedAmount ?? '—' }}</td>
            <td class="px-4 py-2 text-right">{{ s.countedAmount ?? '—' }}</td>
            <td class="px-4 py-2 text-right" :class="s.difference && Number(s.difference) !== 0 ? 'font-semibold text-red-600' : ''">{{ s.difference ?? '—' }}</td>
            <td class="px-4 py-2 text-right"><button class="btn-secondary" @click="openDetail(s)">Ver</button></td>
          </tr>
        </tbody>
      </table>
      <div v-if="historyMeta && historyMeta.totalPages > 1" class="flex justify-end gap-2 border-t border-gray-200 p-3">
        <button class="btn-secondary" :disabled="historyMeta.page <= 1" @click="refresh(historyMeta.page - 1)">Anterior</button>
        <button class="btn-secondary" :disabled="historyMeta.page >= historyMeta.totalPages" @click="refresh(historyMeta.page + 1)">Siguiente</button>
      </div>
    </div>

    <!-- Modales -->
    <BaseModal :open="openModal" title="Abrir caja" @close="openModal = false">
      <div class="space-y-4">
        <FormField label="Monto inicial en efectivo (S/)" required>
          <input v-model.number="openForm.openingAmount" type="number" step="0.01" min="0" class="form-input" />
        </FormField>
        <FormField label="Observaciones">
          <input v-model="openForm.notes" class="form-input" />
        </FormField>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="openModal = false">Cancelar</button>
          <button class="btn-primary" @click="doOpen">Abrir</button>
        </div>
      </div>
    </BaseModal>

    <BaseModal :open="closeModal" title="Cerrar caja (arqueo)" @close="closeModal = false">
      <div class="space-y-4">
        <p class="text-sm text-gray-600">Efectivo esperado: <strong>S/ {{ current?.liveExpectedCash }}</strong></p>
        <FormField label="Efectivo contado físicamente (S/)" required>
          <input v-model.number="closeForm.countedAmount" type="number" step="0.01" min="0" class="form-input" />
        </FormField>
        <p class="text-sm" :class="closeForm.countedAmount - Number(current?.liveExpectedCash ?? 0) !== 0 ? 'text-red-600' : 'text-green-700'">
          Diferencia: S/ {{ (closeForm.countedAmount - Number(current?.liveExpectedCash ?? 0)).toFixed(2) }}
        </p>
        <FormField label="Observaciones del cierre">
          <input v-model="closeForm.notes" class="form-input" />
        </FormField>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="closeModal = false">Cancelar</button>
          <button class="btn-primary" @click="doClose">Cerrar caja</button>
        </div>
      </div>
    </BaseModal>

    <BaseModal :open="movModal" title="Registrar movimiento" @close="movModal = false">
      <div class="space-y-4">
        <FormField label="Tipo" required>
          <select v-model="movForm.movementType" class="form-input">
            <option value="INGRESO">INGRESO</option>
            <option value="EGRESO">EGRESO</option>
          </select>
        </FormField>
        <FormField label="Monto (S/)" required>
          <input v-model.number="movForm.amount" type="number" step="0.01" min="0.01" class="form-input" />
        </FormField>
        <FormField label="Medio de pago">
          <select v-model.number="movForm.paymentMethodId" class="form-input">
            <option :value="null">Efectivo</option>
            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </FormField>
        <FormField label="Concepto" required>
          <input v-model="movForm.concept" class="form-input" maxlength="200" placeholder="Pago de servicios, compra de útiles…" />
        </FormField>
        <div class="flex justify-end gap-3">
          <button class="btn-secondary" @click="movModal = false">Cancelar</button>
          <button class="btn-primary" @click="doMovement">Registrar</button>
        </div>
      </div>
    </BaseModal>

    <BaseModal :open="detailSession !== null" :title="`Movimientos ${detailSession?.sessionNumber}`" @close="detailSession = null">
      <table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-500">
          <tr><th class="py-1">Fecha</th><th class="py-1">Tipo</th><th class="py-1">Concepto</th><th class="py-1 text-right">Monto</th></tr>
        </thead>
        <tbody>
          <tr v-if="detailMovements.length === 0"><td colspan="4" class="py-4 text-center text-gray-400">Sin movimientos.</td></tr>
          <tr v-for="m in detailMovements" :key="m.id" class="border-t border-gray-100">
            <td class="py-1 text-xs">{{ new Date(m.createdAt).toLocaleString() }}</td>
            <td class="py-1" :class="m.movementType === 'INGRESO' ? 'text-green-700' : 'text-red-700'">{{ m.movementType }}</td>
            <td class="py-1">{{ m.concept }} <span class="text-xs text-gray-400">({{ m.reference }})</span></td>
            <td class="py-1 text-right">S/ {{ m.amount }}</td>
          </tr>
        </tbody>
      </table>
    </BaseModal>
  </DefaultLayout>
</template>
