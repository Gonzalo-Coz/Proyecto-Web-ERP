<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import FormField from '@/components/ui/FormField.vue'
import { profileService } from '@/services/profile'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import type { Profile } from '@/types/profile'

const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const profile = ref<Profile | null>(null)

// --- Datos del perfil ---
const form = reactive({ fullName: '', email: '', phone: '' })
const savingProfile = ref(false)
const profileError = ref('')
const profileOk = ref(false)

// --- Contraseña ---
const pwd = reactive({ currentPassword: '', newPassword: '', confirm: '' })
const savingPwd = ref(false)
const pwdError = ref('')
const pwdOk = ref(false)

// --- Avatar ---
const fileInput = ref<HTMLInputElement | null>(null)
const avatarBusy = ref(false)
const avatarError = ref('')

const initials = (): string =>
  (auth.user?.fullName ?? 'U')
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

function hydrate(p: Profile): void {
  profile.value = p
  form.fullName = p.fullName
  form.email = p.email
  form.phone = p.phone ?? ''
  auth.applyProfile({
    fullName: p.fullName,
    email: p.email,
    phone: p.phone,
    avatarUrl: p.avatarUrl,
  })
}

function apiError(error: any, fallback: string): string {
  return error.response?.data?.detail ?? error.response?.data?.message ?? fallback
}

async function saveProfile(): Promise<void> {
  savingProfile.value = true
  profileError.value = ''
  profileOk.value = false
  try {
    const updated = await profileService.update({
      fullName: form.fullName.trim(),
      email: form.email.trim(),
      phone: form.phone.trim() || null,
    })
    hydrate(updated)
    profileOk.value = true
    toast.success('Perfil actualizado.')
  } catch (error: any) {
    profileError.value = apiError(error, 'No se pudieron guardar los cambios.')
  } finally {
    savingProfile.value = false
  }
}

async function changePassword(): Promise<void> {
  pwdError.value = ''
  pwdOk.value = false
  if (pwd.newPassword !== pwd.confirm) {
    pwdError.value = 'La confirmación no coincide con la nueva contraseña.'
    return
  }
  savingPwd.value = true
  try {
    await profileService.changePassword({
      currentPassword: pwd.currentPassword,
      newPassword: pwd.newPassword,
    })
    pwd.currentPassword = ''
    pwd.newPassword = ''
    pwd.confirm = ''
    pwdOk.value = true
    toast.success('Contraseña actualizada.')
  } catch (error: any) {
    pwdError.value = apiError(error, 'No se pudo cambiar la contraseña.')
  } finally {
    savingPwd.value = false
  }
}

function pickAvatar(): void {
  avatarError.value = ''
  fileInput.value?.click()
}

async function onAvatarSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = '' // permite re-seleccionar el mismo archivo
  if (!file) return

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    avatarError.value = 'Formato no admitido. Use JPG, PNG o WEBP.'
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    avatarError.value = 'La imagen supera el tamaño máximo de 5 MB.'
    return
  }

  avatarBusy.value = true
  avatarError.value = ''
  try {
    const updated = await profileService.uploadAvatar(file)
    hydrate(updated)
    toast.success('Fotografía actualizada.')
  } catch (error: any) {
    avatarError.value = apiError(error, 'No se pudo subir la imagen.')
  } finally {
    avatarBusy.value = false
  }
}

async function removeAvatar(): Promise<void> {
  avatarBusy.value = true
  avatarError.value = ''
  try {
    const updated = await profileService.removeAvatar()
    hydrate(updated)
  } catch (error: any) {
    avatarError.value = apiError(error, 'No se pudo quitar la imagen.')
  } finally {
    avatarBusy.value = false
  }
}

onMounted(async () => {
  try {
    hydrate(await profileService.get())
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <DefaultLayout>
    <div class="mb-6">
      <h2 class="text-xl font-bold tracking-tight text-slate-900">Mi Perfil</h2>
      <p class="text-sm text-slate-500">Administra tu información personal, tu fotografía y tu contraseña.</p>
    </div>

    <div v-if="loading" class="card text-sm text-slate-500">Cargando perfil…</div>

    <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Columna: Fotografía -->
      <div class="card flex flex-col items-center text-center lg:col-span-1">
        <img
          v-if="profile?.avatarUrl"
          :src="profile.avatarUrl"
          alt="Fotografía de perfil"
          class="h-32 w-32 rounded-full object-cover ring-4 ring-primary-100"
        />
        <div
          v-else
          class="flex h-32 w-32 items-center justify-center rounded-full bg-primary-800 text-3xl font-bold text-white ring-4 ring-primary-100"
        >
          {{ initials() }}
        </div>

        <p class="mt-4 text-base font-semibold text-slate-800">{{ profile?.fullName }}</p>
        <p class="text-sm text-slate-400">@{{ profile?.username }}</p>

        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onAvatarSelected" />

        <div class="mt-5 flex flex-wrap justify-center gap-2">
          <button type="button" class="btn-primary" :disabled="avatarBusy" @click="pickAvatar">
            {{ avatarBusy ? 'Procesando…' : profile?.avatarUrl ? 'Cambiar foto' : 'Subir foto' }}
          </button>
          <button v-if="profile?.avatarUrl" type="button" class="btn-danger" :disabled="avatarBusy" @click="removeAvatar">
            Quitar
          </button>
        </div>
        <p class="mt-3 text-xs text-slate-400">JPG, PNG o WEBP · hasta 5 MB · se optimiza automáticamente.</p>
        <p v-if="avatarError" class="mt-2 text-xs text-red-600">{{ avatarError }}</p>
      </div>

      <!-- Columna: Datos + Contraseña -->
      <div class="space-y-6 lg:col-span-2">
        <!-- Datos personales -->
        <form class="card space-y-4" @submit.prevent="saveProfile">
          <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Datos personales</h3>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Nombre completo" required>
              <input v-model="form.fullName" type="text" class="form-input" required maxlength="150" />
            </FormField>
            <FormField label="Usuario">
              <input :value="profile?.username" type="text" class="form-input" disabled />
              <p class="mt-1 text-xs text-slate-400">El nombre de usuario no puede modificarse.</p>
            </FormField>
            <FormField label="Correo electrónico" required>
              <input v-model="form.email" type="email" class="form-input" required maxlength="150" />
            </FormField>
            <FormField label="Teléfono">
              <input v-model="form.phone" type="tel" class="form-input" maxlength="30" placeholder="Opcional" />
            </FormField>
          </div>

          <p v-if="profileError" class="text-sm text-red-600">{{ profileError }}</p>
          <p v-else-if="profileOk" class="text-sm font-medium text-emerald-600">Cambios guardados correctamente.</p>

          <div class="flex justify-end">
            <button type="submit" class="btn-primary" :disabled="savingProfile">
              {{ savingProfile ? 'Guardando…' : 'Guardar cambios' }}
            </button>
          </div>
        </form>

        <!-- Contraseña -->
        <form class="card space-y-4" @submit.prevent="changePassword">
          <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Cambiar contraseña</h3>

          <FormField label="Contraseña actual" required>
            <input v-model="pwd.currentPassword" type="password" class="form-input" required autocomplete="current-password" />
          </FormField>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Nueva contraseña" required>
              <input v-model="pwd.newPassword" type="password" class="form-input" required minlength="8" autocomplete="new-password" />
            </FormField>
            <FormField label="Confirmar nueva contraseña" required>
              <input v-model="pwd.confirm" type="password" class="form-input" required minlength="8" autocomplete="new-password" />
            </FormField>
          </div>

          <p v-if="pwdError" class="text-sm text-red-600">{{ pwdError }}</p>
          <p v-else-if="pwdOk" class="text-sm font-medium text-emerald-600">Contraseña actualizada correctamente.</p>

          <div class="flex justify-end">
            <button type="submit" class="btn-primary" :disabled="savingPwd">
              {{ savingPwd ? 'Actualizando…' : 'Actualizar contraseña' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </DefaultLayout>
</template>
