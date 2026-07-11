<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { supplierService } from '@/services/masters'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import type { SupplierItem } from '@/types/masters'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'ruc', label: 'RUC', sortable: true },
  { key: 'businessName', label: 'Razón Social', sortable: true },
  { key: 'contactPerson', label: 'Contacto' },
  { key: 'phone', label: 'Teléfono' },
  { key: 'isActive', label: 'Estado' },
]

const rows = ref<SupplierItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const query = reactive({ page: 1, perPage: 10, search: '', sort: 'businessName', direction: 'asc' as const })

const modalOpen = ref(false)
const editing = ref<SupplierItem | null>(null)
const saving = ref(false)
const formError = ref('')
const emptyForm = {
  ruc: '',
  businessName: '',
  tradeName: null as string | null,
  address: null as string | null,
  city: null as string | null,
  phone: null as string | null,
  email: null as string | null,
  contactPerson: null as string | null,
  isActive: true,
}
const form = reactive({ ...emptyForm })

const confirmTarget = ref<SupplierItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await supplierService.list(query)
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

function openEdit(supplier: SupplierItem): void {
  editing.value = supplier
  const { id, createdAt, ...payload } = supplier
  Object.assign(form, payload)
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await supplierService.update(editing.value.id, { ...form })
    } else {
      await supplierService.create({ ...form })
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value =
      error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar el proveedor.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await supplierService.remove(confirmTarget.value.id)
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
      search-placeholder="Buscar por RUC, razón social o contacto…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('suppliers.list.create')" class="btn-primary" @click="openCreate">
          Nuevo proveedor
        </button>
      </template>

      <template #cell-isActive="{ row }">
        <StatusBadge :active="Boolean(row.isActive)" />
      </template>

      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button
            v-if="auth.can('suppliers.list.edit')"
            class="btn-secondary"
            @click="openEdit(row as unknown as SupplierItem)"
          >
            Editar
          </button>
          <button
            v-if="auth.can('suppliers.list.delete')"
            class="btn-secondary !text-red-600"
            @click="confirmTarget = row as unknown as SupplierItem"
          >
            Eliminar
          </button>
        </div>
      </template>
    </DataTable>

    <BaseModal
      :open="modalOpen"
      :title="editing ? `Editar proveedor: ${editing.businessName}` : 'Nuevo proveedor'"
      @close="modalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <FormField label="RUC" required>
            <input v-model="form.ruc" class="form-input" required maxlength="11" pattern="\d{11}" />
          </FormField>
          <FormField label="Nombre Comercial">
            <input v-model="form.tradeName" class="form-input" maxlength="150" />
          </FormField>
        </div>

        <FormField label="Razón Social" required>
          <input v-model="form.businessName" class="form-input" required maxlength="200" />
        </FormField>

        <FormField label="Dirección">
          <input v-model="form.address" class="form-input" maxlength="200" />
        </FormField>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Ciudad">
            <input v-model="form.city" class="form-input" maxlength="100" />
          </FormField>
          <FormField label="Teléfono">
            <input v-model="form.phone" class="form-input" maxlength="20" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Correo">
            <input v-model="form.email" type="email" class="form-input" maxlength="150" />
          </FormField>
          <FormField label="Persona de contacto">
            <input v-model="form.contactPerson" class="form-input" maxlength="150" />
          </FormField>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Proveedor activo
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
      title="Eliminar proveedor"
      :message="`El proveedor «${confirmTarget?.businessName}» se desactivará y su historial se conservará (eliminación lógica). ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
