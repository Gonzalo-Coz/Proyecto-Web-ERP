<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { securityService } from '@/services/security'
import { useAuthStore } from '@/stores/auth'
import type { PageMeta, TableColumn } from '@/types/common'
import type { RoleItem, UserItem } from '@/types/security'

const auth = useAuthStore()

const columns: TableColumn[] = [
  { key: 'username', label: 'Usuario', sortable: true },
  { key: 'fullName', label: 'Nombre completo', sortable: true },
  { key: 'email', label: 'Correo', sortable: true },
  { key: 'roles', label: 'Roles' },
  { key: 'isActive', label: 'Estado' },
]

const rows = ref<UserItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)
const availableRoles = ref<RoleItem[]>([])

const query = reactive({ page: 1, perPage: 10, search: '', sort: 'username', direction: 'asc' as const })

const modalOpen = ref(false)
const editing = ref<UserItem | null>(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  username: '',
  email: '',
  phone: '',
  fullName: '',
  password: '',
  roleIds: [] as number[],
  isActive: true,
})

const confirmTarget = ref<UserItem | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await securityService.listUsers(query)
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
  Object.assign(form, { username: '', email: '', phone: '', fullName: '', password: '', roleIds: [], isActive: true })
  formError.value = ''
  modalOpen.value = true
}

function openEdit(user: UserItem): void {
  editing.value = user
  Object.assign(form, {
    username: user.username,
    email: user.email ?? '',
    phone: user.phone ?? '',
    fullName: user.fullName,
    password: '',
    roleIds: user.roles.map((r) => r.id),
    isActive: user.isActive,
  })
  formError.value = ''
  modalOpen.value = true
}

async function save(): Promise<void> {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form, email: form.email || null, phone: form.phone || null, password: form.password || null }
    if (editing.value) {
      await securityService.updateUser(editing.value.id, payload)
    } else {
      await securityService.createUser(payload)
    }
    modalOpen.value = false
    await load()
  } catch (error: any) {
    formError.value =
      error.response?.data?.detail ?? error.response?.data?.message ?? 'No se pudo guardar el usuario.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(): Promise<void> {
  if (!confirmTarget.value) return
  try {
    await securityService.deleteUser(confirmTarget.value.id)
    confirmTarget.value = null
    await load()
  } catch {
    confirmTarget.value = null
  }
}

onMounted(async () => {
  await load()
  availableRoles.value = await securityService.listRoles()
})
</script>

<template>
  <DefaultLayout>
    <DataTable
      :columns="columns"
      :rows="rows"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por usuario, nombre o correo…"
      @change="onTableChange"
    >
      <template #toolbar>
        <button v-if="auth.can('security.users.create')" class="btn-primary" @click="openCreate">
          Nuevo usuario
        </button>
      </template>

      <template #cell-roles="{ row }">
        <span class="text-gray-600">{{ (row as unknown as UserItem).roles.map((r) => r.name).join(', ') || '—' }}</span>
      </template>

      <template #cell-isActive="{ row }">
        <StatusBadge :active="Boolean(row.isActive)" />
      </template>

      <template #actions="{ row }">
        <div class="flex justify-end gap-2">
          <button
            v-if="auth.can('security.users.edit')"
            class="btn-secondary"
            @click="openEdit(row as unknown as UserItem)"
          >
            Editar
          </button>
          <button
            v-if="auth.can('security.users.delete')"
            class="btn-secondary !text-red-600"
            @click="confirmTarget = row as unknown as UserItem"
          >
            Eliminar
          </button>
        </div>
      </template>
    </DataTable>

    <BaseModal
      :open="modalOpen"
      :title="editing ? `Editar usuario: ${editing.username}` : 'Nuevo usuario'"
      @close="modalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="save">
        <FormField label="Usuario" required>
          <input v-model="form.username" class="form-input" :disabled="editing !== null" required />
        </FormField>
        <FormField label="Nombre completo" required>
          <input v-model="form.fullName" class="form-input" required />
        </FormField>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Correo electrónico (opcional)">
            <input v-model="form.email" type="email" class="form-input" placeholder="opcional" />
          </FormField>
          <FormField label="Teléfono (opcional)">
            <input v-model="form.phone" class="form-input" maxlength="30" placeholder="opcional" />
          </FormField>
        </div>
        <FormField
          :label="editing ? 'Contraseña (dejar en blanco para no cambiar)' : 'Contraseña'"
          :required="!editing"
        >
          <input
            v-model="form.password"
            type="password"
            class="form-input"
            :required="!editing"
            minlength="8"
            autocomplete="new-password"
          />
        </FormField>
        <FormField label="Roles">
          <div class="space-y-1">
            <label
              v-for="role in availableRoles"
              :key="role.id"
              class="flex items-center gap-2 text-sm text-gray-700"
            >
              <input v-model="form.roleIds" type="checkbox" :value="role.id" />
              {{ role.name }}
            </label>
          </div>
        </FormField>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.isActive" type="checkbox" />
          Usuario activo
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
      title="Eliminar usuario"
      :message="`El usuario «${confirmTarget?.username}» se desactivará y quedará oculto (eliminación lógica). ¿Continuar?`"
      confirm-label="Eliminar"
      danger
      @confirm="confirmDelete"
      @cancel="confirmTarget = null"
    />
  </DefaultLayout>
</template>
