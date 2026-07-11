<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { catalogService } from '@/services/catalogs'
import { useAuthStore } from '@/stores/auth'
import { CATALOG_TYPES, type CatalogItem, type CatalogType } from '@/types/catalogs'

const auth = useAuthStore()

const activeType = ref<CatalogType>('brands')
const items = ref<CatalogItem[]>([])
const loading = ref(false)
const search = ref('')

const modalOpen = ref(false)
const editing = ref<CatalogItem | null>(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ name: '', code: '' as string | null, isActive: true })

const confirmTarget = ref<CatalogItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    items.value = await catalogService.list(activeType.value)
  } finally {
    loading.value = false
  }
}

watch(activeType, () => {
  search.value = ''
  load()
})

function filtered(): CatalogItem[] {
  const term = search.value.trim().toLowerCase()
  if (!term) return items.value
  return items.value.filter(
    (i) => i.name.toLowerCase().includes(term) || (i.code ?? '').toLowerCase().includes(term),
  )
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, { name: '', code: '', isActive: true })
  formError.value = ''
  modalOpen.value = true
}

function openEdit(item: CatalogItem): void {
  editing.value = item
  Object.assign(form, { name: item.name, code: item.code ?? '', isActive: item.isActive })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const payload = { name: form.name, code: form.code || null, isActive: form.isActive }
    if (editing.value) {
      await catalogService.update(activeType.value, editing.value.id, payload)
    } else {
      await catalogService.create(activeType.value, payload)
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value =
      error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await catalogService.remove(activeType.value, confirmTarget.value.id)
  } finally {
    confirmTarget.value = null
    await load()
  }
}

onMounted(load)
</script>

<template>
  <DefaultLayout>
    <div class="mb-4 flex flex-wrap gap-2">
      <button
        v-for="t in CATALOG_TYPES"
        :key="t.key"
        class="rounded-lg px-4 py-2 text-sm font-medium transition"
        :class="activeType === t.key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'"
        @click="activeType = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <div class="card p-0">
      <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4">
        <input v-model="search" type="search" class="form-input max-w-xs" placeholder="Filtrar…" />
        <button v-if="auth.can('settings.catalogs.create')" class="btn-primary" @click="openCreate">
          Nuevo elemento
        </button>
      </div>

      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Nombre</th>
            <th class="px-4 py-3 font-medium">Código</th>
            <th class="px-4 py-3 font-medium">Estado</th>
            <th class="px-4 py-3 text-right font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">Cargando…</td>
          </tr>
          <tr v-else-if="filtered().length === 0">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin elementos.</td>
          </tr>
          <tr v-for="item in filtered()" v-else :key="item.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.name }}</td>
            <td class="px-4 py-3 text-gray-500">{{ item.code ?? '—' }}</td>
            <td class="px-4 py-3"><StatusBadge :active="item.isActive" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <button
                  v-if="auth.can('settings.catalogs.edit')"
                  class="btn-secondary"
                  @click="openEdit(item)"
                >
                  Editar
                </button>
                <button
                  v-if="auth.can('settings.catalogs.delete')"
                  class="btn-secondary !text-red-600"
                  @click="confirmTarget = item"
                >
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <BaseModal
      :open="modalOpen"
      :title="editing ? `Editar: ${editing.name}` : 'Nuevo elemento'"
      @close="modalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="save">
        <FormField label="Nombre" required>
          <input v-model="form.name" class="form-input" required maxlength="100" />
        </FormField>
        <FormField label="Código (opcional, para referencias del sistema)">
          <input v-model="form.code" class="form-input uppercase" maxlength="30" />
        </FormField>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Activo
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
      title="Eliminar elemento"
      :message="`«${confirmTarget?.name}» se desactivará (eliminación lógica). Los registros que lo usan no se verán afectados. ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
