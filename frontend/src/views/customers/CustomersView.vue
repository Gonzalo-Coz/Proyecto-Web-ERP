<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { customerService } from '@/services/masters'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import { DOCUMENT_TYPES, type CustomerItem } from '@/types/masters'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'documentNumber', label: 'Documento', sortable: true },
  { key: 'name', label: 'Nombre / Razón Social', sortable: true },
  { key: 'mobile', label: 'Celular' },
  { key: 'email', label: 'Correo' },
  { key: 'isActive', label: 'Estado' },
]

const rows = ref<CustomerItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'name', direction: 'asc' as const })

const modalOpen = ref(false)
const editing = ref<CustomerItem | null>(null)
const saving = ref(false)
const formError = ref('')
const emptyForm = {
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
  isActive: true,
}
const form = reactive({ ...emptyForm })

const confirmTarget = ref<CustomerItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await customerService.list(query)
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

function openEdit(customer: CustomerItem): void {
  editing.value = customer
  const { id, isLegalEntity, createdAt, ...payload } = customer
  Object.assign(form, payload)
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await customerService.update(editing.value.id, { ...form })
    } else {
      await customerService.create({ ...form })
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value =
      error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar el cliente.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await customerService.remove(confirmTarget.value.id)
  } finally {
    confirmTarget.value = null
    await load()
  }
}

onMounted(load)
</script>

<template>
  <DefaultLayout>
    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por documento, nombre o correo…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('customers.list.create')" class="btn-primary" @click="openCreate">
          Nuevo cliente
        </button>
      </template>

      <template #cell-documentNumber="{ row }">
        <span class="font-medium text-gray-900">{{ row.documentType }}</span>
        <span class="ml-1 text-gray-600">{{ row.documentNumber }}</span>
      </template>

      <template #cell-isActive="{ row }">
        <StatusBadge :active="Boolean(row.isActive)" />
      </template>

      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button
            v-if="auth.can('customers.list.edit')"
            class="btn-secondary"
            @click="openEdit(row as unknown as CustomerItem)"
          >
            Editar
          </button>
          <button
            v-if="auth.can('customers.list.delete')"
            class="btn-secondary !text-red-600"
            @click="confirmTarget = row as unknown as CustomerItem"
          >
            Eliminar
          </button>
        </div>
      </template>
    </DataTable>

    <BaseModal
      :open="modalOpen"
      :title="editing ? `Editar cliente: ${editing.name}` : 'Nuevo cliente'"
      @close="modalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Tipo de documento" required>
            <select v-model="form.documentType" class="form-input" required>
              <option v-for="dt in DOCUMENT_TYPES" :key="dt" :value="dt">{{ dt }}</option>
            </select>
          </FormField>
          <FormField label="Número de documento" required>
            <input v-model="form.documentNumber" class="form-input" required maxlength="20" />
          </FormField>
        </div>

        <FormField
          :label="form.documentType === 'RUC' ? 'Razón Social' : 'Nombres y Apellidos'"
          required
        >
          <input v-model="form.name" class="form-input" required maxlength="200" />
        </FormField>

        <FormField label="Nombre Comercial">
          <input v-model="form.tradeName" class="form-input" maxlength="150" />
        </FormField>

        <FormField label="Dirección">
          <input v-model="form.address" class="form-input" maxlength="200" />
        </FormField>

        <div class="grid grid-cols-3 gap-4">
          <FormField label="Distrito">
            <input v-model="form.district" class="form-input" maxlength="100" />
          </FormField>
          <FormField label="Provincia">
            <input v-model="form.province" class="form-input" maxlength="100" />
          </FormField>
          <FormField label="Departamento">
            <input v-model="form.department" class="form-input" maxlength="100" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Teléfono">
            <input v-model="form.phone" class="form-input" maxlength="20" />
          </FormField>
          <FormField label="Celular">
            <input v-model="form.mobile" class="form-input" maxlength="20" />
          </FormField>
        </div>

        <FormField label="Correo electrónico">
          <input v-model="form.email" type="email" class="form-input" maxlength="150" />
        </FormField>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Cliente activo
        </label>

        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <ConfirmDialog
      :open="confirmTarget !== null"
      title="Eliminar cliente"
      :message="`El cliente «${confirmTarget?.name}» se desactivará y su historial se conservará (eliminación lógica). ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
