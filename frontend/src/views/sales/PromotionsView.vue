<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { promotionService } from '@/services/promotions'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { PromotionItem } from '@/types/promotions'

/**
 * Promociones (versión simple): un descuento con nombre y vigencia que luego se
 * aplica, opcionalmente, a una venta. Sin alcance por marca/categoría ni
 * bonificaciones (esa complejidad se retiró a pedido del propietario).
 */
const auth = useAuthStore()
const toast = useToast()

const columns: TableColumn[] = [
  { key: 'code', label: 'Código', sortable: true },
  { key: 'name', label: 'Nombre', sortable: true },
  { key: 'discountPercent', label: 'Descuento' },
  { key: 'vigencia', label: 'Vigencia' },
  { key: 'isActive', label: 'Estado' },
]

const rows = ref<PromotionItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'name', direction: 'asc' as 'asc' | 'desc' })

const modalOpen = ref(false)
const editing = ref<PromotionItem | null>(null)
const saving = ref(false)
const formError = ref('')

const today = new Date().toISOString().slice(0, 10)
const emptyForm = {
  code: '',
  name: '',
  discountPercent: null as number | null,
  startDate: today,
  endDate: today,
  isActive: true,
}
const form = reactive({ ...emptyForm })
const confirmTarget = ref<PromotionItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await promotionService.list(query)
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

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyForm)
  formError.value = ''
  modalOpen.value = true
}

function openEdit(promo: PromotionItem): void {
  editing.value = promo
  Object.assign(form, {
    code: promo.code,
    name: promo.name,
    discountPercent: promo.discountPercent !== null ? Number(promo.discountPercent) : null,
    startDate: promo.startDate,
    endDate: promo.endDate,
    isActive: promo.isActive,
  })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    // Se envían valores fijos para el backend (que sigue soportando el modelo
    // completo): una promoción simple = descuento para todos los productos.
    const payload = {
      code: form.code,
      name: form.name,
      type: 'DISCOUNT' as const,
      discountPercent: form.discountPercent,
      scopeType: 'ALL' as const,
      scopeRefId: null,
      bonusSubjectType: null,
      bonusSubjectId: null,
      bonusQuantity: null,
      startDate: form.startDate,
      endDate: form.endDate,
      isActive: form.isActive,
    }
    if (editing.value) {
      await promotionService.update(editing.value.id, payload)
    } else {
      await promotionService.create(payload)
    }
    modalOpen.value = false
    await load()
    toast.success('Promoción guardada.')
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar la promoción.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await promotionService.remove(confirmTarget.value.id)
    confirmTarget.value = null
    await load()
    toast.success('Promoción eliminada.')
  } catch {
    confirmTarget.value = null
    toast.error('No se pudo eliminar la promoción.')
  }
}

onMounted(load)
</script>

<template>
  <DefaultLayout>
    <div class="mb-6 flex items-end justify-between">
      <div>
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Promociones</h2>
        <p class="text-sm text-slate-500">Descuentos con nombre y vigencia. Se aplican de forma opcional al registrar una venta.</p>
      </div>
      <button v-if="auth.can('sales.promotions.create')" class="btn-primary" @click="openCreate">Nueva promoción</button>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows as unknown as Record<string, unknown>[]"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por código o nombre…"
      @change="onTableChange"
    >
      <template #cell-discountPercent="{ row }">
        <span class="chip bg-primary-50 text-primary-700">{{ Number((row as PromotionItem).discountPercent).toFixed(0) }}% dcto.</span>
      </template>
      <template #cell-vigencia="{ row }">
        <span class="whitespace-nowrap text-xs text-slate-600">{{ (row as PromotionItem).startDate }} → {{ (row as PromotionItem).endDate }}</span>
      </template>
      <template #cell-isActive="{ row }">
        <StatusBadge :active="Boolean(row.isActive)" />
      </template>
      <template #actions="{ row }">
        <button v-if="auth.can('sales.promotions.edit')" class="btn-secondary" @click="openEdit(row as unknown as PromotionItem)">Editar</button>
        <button
          v-if="auth.can('sales.promotions.delete')"
          class="btn-danger ml-2"
          @click="confirmTarget = row as unknown as PromotionItem"
        >
          Eliminar
        </button>
      </template>
    </DataTable>

    <BaseModal :open="modalOpen" :title="editing ? `Editar promoción: ${editing.name}` : 'Nueva promoción'" size="lg" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Código" required>
            <input v-model="form.code" class="form-input" maxlength="30" placeholder="VERANO2026" required />
          </FormField>
          <FormField label="Descuento (%)" required>
            <input v-model.number="form.discountPercent" type="number" step="0.01" min="0" max="100" class="form-input" required />
          </FormField>
        </div>
        <FormField label="Nombre" required>
          <input v-model="form.name" class="form-input" maxlength="120" placeholder="Campaña de verano" required />
        </FormField>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Vigencia desde" required>
            <input v-model="form.startDate" type="date" class="form-input" required />
          </FormField>
          <FormField label="Vigencia hasta" required>
            <input v-model="form.endDate" type="date" class="form-input" required />
          </FormField>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Promoción activa
        </label>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </form>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Eliminar promoción"
      :message="`¿Eliminar la promoción &quot;${confirmTarget?.name}&quot;?`"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
