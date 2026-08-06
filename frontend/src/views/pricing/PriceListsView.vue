<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { pricingService } from '@/services/pricing'
import { sparePartService } from '@/services/inventory'
import { modelService } from '@/services/motorcycles'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { PriceListItem, PriceListLine } from '@/types/pricing'
import type { SparePartItem } from '@/types/inventory'
import type { ModelItem } from '@/types/motorcycles'

const auth = useAuthStore()
const toast = useToast()

const columns: TableColumn[] = [
  { key: 'code', label: 'Código', sortable: true },
  { key: 'name', label: 'Nombre', sortable: true },
  { key: 'itemCount', label: 'Productos' },
  { key: 'isDefault', label: 'Predeterminada' },
  { key: 'isActive', label: 'Estado' },
]

const rows = ref<PriceListItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'name', direction: 'asc' as 'asc' | 'desc' })

// Catálogos de productos para el selector de líneas
const spareParts = ref<SparePartItem[]>([])
const models = ref<ModelItem[]>([])

const modalOpen = ref(false)
const editing = ref<PriceListItem | null>(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ code: '', name: '', isDefault: false, isActive: true })
const items = ref<PriceListLine[]>([])

// Alta de línea
const draft = reactive({ subjectType: 'spare_part', subjectId: null as number | null, price: null as number | null })

const confirmTarget = ref<PriceListItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await pricingService.listPriceLists(query)
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

const productOptions = computed(() =>
  draft.subjectType === 'spare_part'
    ? spareParts.value.map((s) => ({ id: s.id, label: `${s.internalCode} · ${s.description}` }))
    : models.value.map((m) => ({ id: m.id, label: m.fullName })),
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, { code: '', name: '', isDefault: false, isActive: true })
  items.value = []
  formError.value = ''
  modalOpen.value = true
}

async function openEdit(list: PriceListItem): Promise<void> {
  editing.value = list
  formError.value = ''
  const detail = await pricingService.getPriceList(list.id)
  Object.assign(form, { code: detail.code, name: detail.name, isDefault: detail.isDefault, isActive: detail.isActive })
  items.value = detail.items.map((i) => ({ ...i, price: Number(i.price) }))
  modalOpen.value = true
}

function addLine(): void {
  if (draft.subjectId === null || draft.price === null) return
  if (items.value.some((i) => i.subjectType === draft.subjectType && i.subjectId === draft.subjectId)) {
    formError.value = 'Ese producto ya está en la lista.'
    return
  }
  const label = productOptions.value.find((o) => o.id === draft.subjectId)?.label ?? ''
  items.value.push({ subjectType: draft.subjectType, subjectId: draft.subjectId, subjectLabel: label, price: draft.price })
  draft.subjectId = null
  draft.price = null
  formError.value = ''
}

function removeLine(index: number): void {
  items.value.splice(index, 1)
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      ...form,
      items: items.value.map((i) => ({ subjectType: i.subjectType, subjectId: i.subjectId, price: Number(i.price) })),
    }
    if (editing.value) {
      await pricingService.updatePriceList(editing.value.id, payload)
    } else {
      await pricingService.createPriceList(payload)
    }
    modalOpen.value = false
    await load()
    toast.success('Lista de precios guardada.')
  } catch (error: any) {
    formError.value = error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar la lista.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await pricingService.removePriceList(confirmTarget.value.id)
    confirmTarget.value = null
    await load()
    toast.success('Lista eliminada.')
  } catch {
    confirmTarget.value = null
    toast.error('No se pudo eliminar la lista.')
  }
}

const money = (v: number | string): string => `S/ ${Number(v).toFixed(2)}`

onMounted(async () => {
  await load()
  const [sp, md] = await Promise.all([
    sparePartService.list({ page: 1, perPage: 100, search: '', sort: 'description', direction: 'asc' }),
    modelService.list({ page: 1, perPage: 100, search: '', sort: 'model', direction: 'asc' }),
  ])
  spareParts.value = sp.data
  models.value = md.data
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-6 flex items-end justify-between">
      <div>
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Listas de Precios</h2>
        <p class="text-sm text-slate-500">Define precios por lista y asígnalas a clientes; la venta toma el precio de su lista.</p>
      </div>
      <button v-if="auth.can('pricing.price_lists.create')" class="btn-primary" @click="openCreate">Nueva lista</button>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows as unknown as Record<string, unknown>[]"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por código o nombre…"
      @change="onTableChange"
    >
      <template #cell-isDefault="{ row }">
        <span v-if="row.isDefault" class="chip bg-primary-50 text-primary-700">Predeterminada</span>
        <span v-else class="text-slate-400">—</span>
      </template>
      <template #cell-isActive="{ row }">
        <StatusBadge :active="Boolean(row.isActive)" />
      </template>
      <template #actions="{ row }">
        <button v-if="auth.can('pricing.price_lists.edit')" class="btn-secondary" @click="openEdit(row as unknown as PriceListItem)">Editar</button>
        <button
          v-if="auth.can('pricing.price_lists.delete')"
          class="btn-danger ml-2"
          @click="confirmTarget = row as unknown as PriceListItem"
        >
          Eliminar
        </button>
      </template>
    </DataTable>

    <BaseModal :open="modalOpen" :title="editing ? `Editar lista: ${editing.name}` : 'Nueva lista de precios'" size="xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Código" required>
            <input v-model="form.code" class="form-input" maxlength="30" placeholder="MAYORISTA" required />
          </FormField>
          <FormField label="Nombre" required>
            <input v-model="form.name" class="form-input" maxlength="100" placeholder="Precios mayorista" required />
          </FormField>
        </div>
        <div class="flex gap-6">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.isDefault" type="checkbox" />
            Predeterminada
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.isActive" type="checkbox" />
            Activa
          </label>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Precios por producto</p>
          <div class="flex flex-wrap items-end gap-2">
            <select v-model="draft.subjectType" class="form-input w-40" @change="draft.subjectId = null">
              <option value="spare_part">Repuesto</option>
              <option value="motorcycle_model">Modelo de moto</option>
            </select>
            <select v-model.number="draft.subjectId" class="form-input min-w-[16rem] flex-1">
              <option :value="null">Selecciona un producto…</option>
              <option v-for="o in productOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
            </select>
            <input v-model.number="draft.price" type="number" step="0.01" min="0" class="form-input w-32" placeholder="Precio" />
            <button type="button" class="btn-secondary" @click="addLine">Agregar</button>
          </div>

          <table v-if="items.length" class="mt-3 w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 text-left">
                <th class="px-2 py-1.5">Producto</th>
                <th class="px-2 py-1.5 text-right">Precio</th>
                <th class="w-10"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(it, idx) in items" :key="idx">
                <td class="px-2 py-1.5">{{ it.subjectLabel }}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">{{ money(it.price) }}</td>
                <td class="px-2 py-1.5 text-right">
                  <button type="button" class="text-red-500 hover:underline" @click="removeLine(idx)">Quitar</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="mt-3 text-xs text-slate-400">Sin productos. Los que no estén en la lista usarán su precio base.</p>
        </div>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </form>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Eliminar lista de precios"
      :message="`¿Eliminar la lista &quot;${confirmTarget?.name}&quot;? Los clientes que la usen volverán al precio base.`"
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
