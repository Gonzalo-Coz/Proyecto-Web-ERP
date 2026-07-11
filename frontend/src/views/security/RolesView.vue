<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { securityService } from '@/services/security'
import { useAuthStore } from '@/stores/auth'
import type { PermissionCatalog, RoleItem } from '@/types/security'

const auth = useAuthStore()

const roles = ref<RoleItem[]>([])
const catalog = ref<PermissionCatalog>({})
const loading = ref(false)

const modalOpen = ref(false)
const editing = ref<RoleItem | null>(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  code: '',
  name: '',
  description: '' as string | null,
  permissionCodes: [] as string[],
  isActive: true,
})

const confirmTarget = ref<RoleItem | null>(null)

const moduleLabels = computed(() => Object.keys(catalog.value).sort())

async function load(): Promise<void> {
  loading.value = true
  try {
    roles.value = await securityService.listRoles()
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, { code: '', name: '', description: '', permissionCodes: [], isActive: true })
  formError.value = ''
  modalOpen.value = true
}

function openEdit(role: RoleItem): void {
  editing.value = role
  Object.assign(form, {
    code: role.code,
    name: role.name,
    description: role.description ?? '',
    permissionCodes: [...role.permissionCodes],
    isActive: role.isActive,
  })
  formError.value = ''
  modalOpen.value = true
}

function toggleScreen(codes: string[], checked: boolean): void {
  const set = new Set(form.permissionCodes)
  codes.forEach((code) => (checked ? set.add(code) : set.delete(code)))
  form.permissionCodes = [...set]
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form, description: form.description || null }
    if (editing.value) {
      await securityService.updateRole(editing.value.id, payload)
    } else {
      await securityService.createRole(payload)
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value =
      error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar el rol.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await securityService.deleteRole(confirmTarget.value.id)
  } finally {
    confirmTarget.value = null
    await load()
  }
}

onMounted(async () => {
  await load()
  catalog.value = await securityService.listPermissions()
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-4 flex justify-end">
      <button v-if="auth.can('security.roles.create')" class="btn-primary" @click="openCreate">
        Nuevo rol
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>
      <div v-for="role in roles" v-else :key="role.id" class="card">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">{{ role.name }}</h3>
            <p class="text-xs text-gray-500">{{ role.code }}</p>
          </div>
          <StatusBadge :active="role.isActive" />
        </div>
        <p class="mt-2 min-h-10 text-sm text-gray-600">{{ role.description || '—' }}</p>
        <p class="mt-2 text-xs text-gray-500">
          {{ role.isSuperAdmin ? 'Acceso total (superadministrador)' : `${role.permissionCodes.length} permiso(s)` }}
        </p>
        <div v-if="!role.isSuperAdmin" class="mt-4 flex gap-2">
          <button v-if="auth.can('security.roles.edit')" class="btn-secondary" @click="openEdit(role)">
            Editar
          </button>
          <button
            v-if="auth.can('security.roles.delete')"
            class="btn-secondary !text-red-600"
            @click="confirmTarget = role"
          >
            Eliminar
          </button>
        </div>
      </div>
    </div>

    <BaseModal
      :open="modalOpen"
      :title="editing ? `Editar rol: ${editing.name}` : 'Nuevo rol'"
      @close="modalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="save">
        <FormField label="Código" required>
          <input
            v-model="form.code"
            class="form-input uppercase"
            :disabled="editing !== null"
            required
            maxlength="50"
          />
        </FormField>
        <FormField label="Nombre" required>
          <input v-model="form.name" class="form-input" required />
        </FormField>
        <FormField label="Descripción">
          <textarea v-model="form.description as string" class="form-input" rows="2" />
        </FormField>

        <FormField label="Permisos">
          <div class="max-h-64 space-y-4 overflow-y-auto rounded-lg border border-gray-200 p-3">
            <div v-for="module in moduleLabels" :key="module">
              <div class="mb-1 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase text-gray-500">{{ module }}</p>
                <button
                  type="button"
                  class="text-xs text-primary-600 hover:underline"
                  @click="toggleScreen(catalog[module].map((p) => p.code), true)"
                >
                  Marcar todo
                </button>
              </div>
              <div class="grid grid-cols-2 gap-1">
                <label
                  v-for="permission in catalog[module]"
                  :key="permission.code"
                  class="flex items-center gap-2 text-sm text-gray-700"
                >
                  <input v-model="form.permissionCodes" type="checkbox" :value="permission.code" />
                  {{ permission.name }}
                </label>
              </div>
            </div>
          </div>
        </FormField>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Rol activo
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
      title="Eliminar rol"
      :message="`El rol «${confirmTarget?.name}» se desactivará (eliminación lógica). Los usuarios que lo tengan asignado perderán sus permisos. ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
