<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UbigeoSelect from '@/components/ui/UbigeoSelect.vue'
import ImportModal from '@/components/ui/ImportModal.vue'
import { customerService, customerTypeService } from '@/services/masters'
import { pricingService } from '@/services/pricing'
import { lookupService } from '@/services/lookup'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { PageMeta, TableColumn } from '@/types/common'
import type { PriceListItem } from '@/types/pricing'
import { DOCUMENT_TYPES, type CustomerItem, type CustomerTypeItem } from '@/types/masters'

const auth = useAuthStore()
const toast = useToast()
const lookingUp = ref(false)

/** Autocompletado por DNI/RUC (integración APISPERU) sobre el formulario. */
async function autoFillFromDocument(): Promise<void> {
  const doc = form.documentNumber.trim()
  if (form.documentType !== 'DNI' && form.documentType !== 'RUC') {
    toast.info('La consulta automática solo aplica a DNI y RUC.')
    return
  }
  lookingUp.value = true
  try {
    if (form.documentType === 'DNI') {
      const p = await lookupService.dni(doc)
      form.name = p.nombreCompleto
      toast.success('Datos del DNI cargados.')
    } else {
      const c = await lookupService.ruc(doc)
      form.name = c.razonSocial
      form.tradeName = c.nombreComercial ?? form.tradeName
      form.address = c.direccion ?? form.address
      form.district = c.distrito ?? form.district
      form.province = c.provincia ?? form.province
      form.department = c.departamento ?? form.department
      toast.success('Datos del RUC cargados.')
    }
  } catch (error: any) {
    toast.error(error.response?.data?.message ?? 'No se pudo consultar el documento.')
  } finally {
    lookingUp.value = false
  }
}

const columns: TableColumn[] = [
  { key: 'documentNumber', label: 'Documento', sortable: true },
  { key: 'name', label: 'Nombre / Razón Social', sortable: true },
  { key: 'customerType', label: 'Tipo' },
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
  priceListId: null as number | null,
  customerTypeId: null as number | null,
  isActive: true,
}
const form = reactive({ ...emptyForm })
const priceLists = ref<PriceListItem[]>([])
const customerTypes = ref<CustomerTypeItem[]>([])

async function loadCustomerTypes(): Promise<void> {
  try {
    customerTypes.value = await customerTypeService.list()
  } catch {
    customerTypes.value = []
  }
}

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
  const { id, isLegalEntity, createdAt, priceListName, customerTypeLabel, discountPercent, ...payload } =
    customer
  Object.assign(form, payload)
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    // El estado activo/inactivo se maneja por código, no desde el formulario:
    // todo cliente creado/editado queda ACTIVO para que siempre pueda facturarse.
    const payload = { ...form, isActive: true }
    if (editing.value) {
      await customerService.update(editing.value.id, payload)
    } else {
      await customerService.create(payload)
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

const importOpen = ref(false)

// ============ Gestión de tipos de cliente (catálogo administrable) ============
const typesModalOpen = ref(false)
const typeForm = reactive({ id: null as number | null, name: '', discountPercent: 0, isActive: true })
const typeSaving = ref(false)
const typeError = ref('')

function openTypesManager(): void {
  resetTypeForm()
  typeError.value = ''
  typesModalOpen.value = true
}

function resetTypeForm(): void {
  Object.assign(typeForm, { id: null, name: '', discountPercent: 0, isActive: true })
}

function editType(t: CustomerTypeItem): void {
  Object.assign(typeForm, { id: t.id, name: t.name, discountPercent: Number(t.discountPercent), isActive: t.isActive })
}

async function saveType(): Promise<void> {
  typeSaving.value = true
  typeError.value = ''
  try {
    const payload = { name: typeForm.name.trim(), discountPercent: Number(typeForm.discountPercent), isActive: typeForm.isActive }
    if (typeForm.id) {
      await customerTypeService.update(typeForm.id, payload)
    } else {
      await customerTypeService.create(payload)
    }
    await loadCustomerTypes()
    resetTypeForm()
    toast.success('Tipo de cliente guardado.')
  } catch (e: any) {
    typeError.value = e.response?.data?.detail ?? e.response?.data?.message ?? 'No se pudo guardar el tipo.'
  } finally {
    typeSaving.value = false
  }
}

async function removeType(t: CustomerTypeItem): Promise<void> {
  try {
    await customerTypeService.remove(t.id)
    await loadCustomerTypes()
    if (typeForm.id === t.id) resetTypeForm()
    toast.success('Tipo eliminado.')
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? 'No se pudo eliminar el tipo.')
  }
}

onMounted(async () => {
  await load()
  await loadCustomerTypes()
  try {
    priceLists.value = (await pricingService.listPriceLists({ perPage: 100 })).data.filter((l) => l.isActive)
  } catch {
    priceLists.value = []
  }
})
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
        <button v-if="auth.can('customers.list.edit')" class="btn-secondary" @click="openTypesManager">
          Tipos de cliente
        </button>
        <button v-if="auth.can('customers.list.create')" class="btn-secondary" @click="importOpen = true">
          Importar Excel
        </button>
        <button v-if="auth.can('customers.list.create')" class="btn-primary" @click="openCreate">
          Nuevo cliente
        </button>
      </template>

      <template #cell-documentNumber="{ row }">
        <span class="font-medium text-gray-900">{{ row.documentType }}</span>
        <span class="ml-1 text-gray-600">{{ row.documentNumber }}</span>
      </template>

      <template #cell-customerType="{ row }">
        <span class="text-gray-700">{{ row.customerTypeLabel ?? '—' }}</span>
        <span v-if="Number(row.discountPercent) > 0" class="ml-1 text-xs font-semibold text-green-600">
          {{ Number(row.discountPercent).toFixed(0) }}%
        </span>
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
            <div class="flex gap-2">
              <input
                v-model="form.documentNumber"
                class="form-input"
                required
                maxlength="20"
                @keyup.enter.prevent="autoFillFromDocument"
              />
              <button
                type="button"
                class="btn-secondary shrink-0"
                :disabled="lookingUp || !form.documentNumber"
                :title="`Consultar ${form.documentType} en línea`"
                @click="autoFillFromDocument"
              >
                {{ lookingUp ? '…' : 'Buscar' }}
              </button>
            </div>
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

        <UbigeoSelect
          v-model:department="form.department"
          v-model:province="form.province"
          v-model:district="form.district"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Teléfono">
            <input v-model="form.phone" class="form-input" maxlength="20" />
          </FormField>
          <FormField label="Correo electrónico">
            <input v-model="form.email" type="email" class="form-input" maxlength="150" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Tipo de cliente">
            <select v-model="form.customerTypeId" class="form-input">
              <option :value="null">Sin tipo (0% dcto.)</option>
              <option v-for="ct in customerTypes" :key="ct.id" :value="ct.id">
                {{ ct.name }}<template v-if="Number(ct.discountPercent) > 0"> — {{ Number(ct.discountPercent).toFixed(0) }}% dcto.</template>
              </option>
            </select>
          </FormField>
          <FormField label="Lista de precios">
            <select v-model="form.priceListId" class="form-input">
              <option :value="null">Predeterminada / precio base</option>
              <option v-for="pl in priceLists" :key="pl.id" :value="pl.id">
                {{ pl.name }}<template v-if="pl.isDefault"> (predeterminada)</template>
              </option>
            </select>
          </FormField>
        </div>

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

    <ImportModal
      :open="importOpen"
      title="Importar clientes desde Excel/CSV"
      code-header="Documento"
      :download="customerService.downloadImportTemplate"
      :run="customerService.importFile"
      @close="importOpen = false"
      @imported="load"
    >
      <template #help>
        <p class="text-xs">
          Columnas: Tipo de Documento · Número de Documento · Nombre o Razón Social · Nombre Comercial ·
          Dirección · Distrito · Provincia · Departamento · Teléfono · Email · Activo. La clave para crear o
          actualizar es el <strong>número de documento</strong>. Si dejas el tipo vacío, se infiere del número
          (8 dígitos → DNI, 11 → RUC).
        </p>
      </template>
    </ImportModal>

    <BaseModal :open="typesModalOpen" title="Tipos de cliente y descuentos" @close="typesModalOpen = false">
      <div class="space-y-4">
        <p class="text-sm text-gray-500">
          Crea o edita tipos de cliente y su % de descuento. El descuento se aplica automáticamente al elegir el
          cliente en una venta (editable por línea).
        </p>

        <div class="overflow-hidden rounded-lg border border-gray-200">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
              <tr>
                <th class="px-3 py-2">Tipo</th>
                <th class="px-3 py-2 text-right">Descuento</th>
                <th class="px-3 py-2">Estado</th>
                <th class="px-3 py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in customerTypes" :key="t.id" class="border-t border-gray-100">
                <td class="px-3 py-2 font-medium text-gray-800">{{ t.name }}</td>
                <td class="px-3 py-2 text-right">{{ Number(t.discountPercent).toFixed(0) }}%</td>
                <td class="px-3 py-2">
                  <StatusBadge :active="Boolean(t.isActive)" />
                </td>
                <td class="px-3 py-2 text-right">
                  <button class="btn-secondary" @click="editType(t)">Editar</button>
                  <button class="btn-secondary ml-2 !text-red-600" @click="removeType(t)">Eliminar</button>
                </td>
              </tr>
              <tr v-if="customerTypes.length === 0">
                <td colspan="4" class="px-3 py-4 text-center text-gray-400">Aún no hay tipos de cliente.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <form class="grid grid-cols-12 items-end gap-3 rounded-lg bg-gray-50 p-3" @submit.prevent="saveType">
          <FormField label="Nombre del tipo" class="col-span-6">
            <input v-model="typeForm.name" class="form-input" required maxlength="60" placeholder="Ej. Mayorista" />
          </FormField>
          <FormField label="% Descuento" class="col-span-3">
            <input v-model.number="typeForm.discountPercent" type="number" step="0.5" min="0" max="100" class="form-input" />
          </FormField>
          <label class="col-span-3 flex items-center gap-2 pb-2 text-sm text-gray-700">
            <input v-model="typeForm.isActive" type="checkbox" /> Activo
          </label>
          <div class="col-span-12 flex items-center justify-between">
            <p v-if="typeError" class="text-sm text-red-600">{{ typeError }}</p>
            <p v-else class="text-xs text-gray-400">{{ typeForm.id ? 'Editando un tipo existente' : 'Nuevo tipo' }}</p>
            <div class="flex gap-2">
              <button v-if="typeForm.id" type="button" class="btn-secondary" @click="resetTypeForm">Nuevo</button>
              <button type="submit" class="btn-primary" :disabled="typeSaving">
                {{ typeSaving ? 'Guardando…' : typeForm.id ? 'Actualizar' : 'Agregar' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </BaseModal>
  </DefaultLayout>
</template>
