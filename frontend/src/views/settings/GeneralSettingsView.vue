
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import FormField from '@/components/ui/FormField.vue'
import UbigeoSelect from '@/components/ui/UbigeoSelect.vue'
import api from '@/services/api'
import { companyService, mediaUrl } from '@/services/company'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const toast = useToast()
const canEdit = computed(() => auth.can('settings.general.edit'))

const uploadingKind = ref<'full' | 'icon' | ''>('')

async function onLogoSelected(kind: 'full' | 'icon', event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploadingKind.value = kind
  try {
    const { path } = await companyService.uploadLogo(kind, file)
    form[kind === 'icon' ? 'company.logo_icon_path' : 'company.logo_full_path'] = path
    toast.success('Logo actualizado.')
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? 'No se pudo subir el logo.')
  } finally {
    uploadingKind.value = ''
    input.value = ''
  }
}

async function removeLogo(kind: 'full' | 'icon'): Promise<void> {
  try {
    await companyService.removeLogo(kind)
    form[kind === 'icon' ? 'company.logo_icon_path' : 'company.logo_full_path'] = ''
    toast.success('Logo eliminado.')
  } catch (e: any) {
    toast.error(e.response?.data?.detail ?? 'No se pudo eliminar el logo.')
  }
}

const form = reactive<Record<string, string>>({})
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const errorMsg = ref('')

async function load(): Promise<void> {
  loading.value = true
  const { data } = await api.get('/settings')
  Object.assign(form, data.data)
  loading.value = false
}

/* ===== Tipo de cambio del día (SUNAT) ===== */
const ex = reactive({ sell: null as number | null, buy: null as number | null, date: '', source: '', stale: false })
const exLoading = ref(false)
const exManualSell = ref<number | null>(null)
const exManualBuy = ref<number | null>(null)

async function loadRate(): Promise<void> {
  exLoading.value = true
  try {
    const { data } = await api.get('/exchange-rate')
    Object.assign(ex, data)
    exManualSell.value = data.sell
    exManualBuy.value = data.buy
  } catch {
    /* queda editable manual */
  } finally {
    exLoading.value = false
  }
}

async function saveRate(): Promise<void> {
  if (!exManualSell.value || exManualSell.value <= 0) return
  exLoading.value = true
  try {
    const { data } = await api.put('/exchange-rate', { sell: exManualSell.value, buy: exManualBuy.value })
    Object.assign(ex, data)
    exManualBuy.value = data.buy
    message.value = 'Tipo de cambio guardado.'
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo guardar el tipo de cambio.'
  } finally {
    exLoading.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  message.value = ''
  errorMsg.value = ''
  try {
    await api.put('/settings', { ...form })
    message.value = 'Perfil de la empresa guardado correctamente.'
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo guardar el perfil.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load()
  loadRate()
})
</script>

<template>
  <DefaultLayout>
    <div class="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Perfil de la Empresa</h1>
        <p class="text-sm text-gray-500">
          Datos oficiales de la empresa usados en comprobantes, reportes y en todo el sistema.
          <span v-if="!canEdit" class="font-medium text-amber-600">Solo lectura — se requiere permiso de administrador para editar.</span>
        </p>
      </div>

      <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>

      <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Datos fiscales -->
        <section class="card">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-500">Identidad y datos fiscales</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Razón Social">
              <input v-model="form['company.name']" class="form-input" :disabled="!canEdit" maxlength="200" />
            </FormField>
            <FormField label="Nombre Comercial">
              <input v-model="form['company.trade_name']" class="form-input" :disabled="!canEdit" maxlength="150" />
            </FormField>
            <FormField label="RUC">
              <input v-model="form['company.ruc']" class="form-input" :disabled="!canEdit" maxlength="11" />
            </FormField>
            <FormField label="Representante legal">
              <input v-model="form['company.legal_rep']" class="form-input" :disabled="!canEdit" maxlength="200" />
            </FormField>
          </div>
        </section>

        <!-- Ubicación -->
        <section class="card">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-500">Ubicación</h2>
          <FormField label="Dirección">
            <input v-model="form['company.address']" class="form-input" :disabled="!canEdit" maxlength="200" />
          </FormField>
          <div class="mt-4">
            <UbigeoSelect
              v-if="canEdit"
              v-model:department="form['company.department']"
              v-model:province="form['company.province']"
              v-model:district="form['company.district']"
            />
            <p v-else class="text-sm text-gray-600">
              {{ [form['company.district'], form['company.province'], form['company.department']].filter(Boolean).join(' · ') || '—' }}
            </p>
          </div>
        </section>

        <!-- Contacto -->
        <section class="card">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-500">Contacto</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Teléfono fijo">
              <input v-model="form['company.phone']" class="form-input" :disabled="!canEdit" maxlength="20" />
            </FormField>
            <FormField label="Celular">
              <input v-model="form['company.mobile']" class="form-input" :disabled="!canEdit" maxlength="20" />
            </FormField>
            <FormField label="Correo electrónico">
              <input v-model="form['company.email']" type="email" class="form-input" :disabled="!canEdit" maxlength="150" />
            </FormField>
            <FormField label="Sitio web">
              <input v-model="form['company.website']" class="form-input" :disabled="!canEdit" maxlength="150" placeholder="https://…" />
            </FormField>
          </div>
        </section>

        <!-- Logo de la empresa -->
        <section class="card">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-500">Logo de la empresa</h2>
          <p class="mb-3 text-xs text-gray-400">
            Se usa en todo el sistema: inicio de sesión, cabecera y en los comprobantes (facturas, boletas y cotizaciones).
          </p>
          <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
            <div class="flex h-28 w-72 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
              <img v-if="form['company.logo_full_path']" :src="mediaUrl(form['company.logo_full_path'])" class="max-h-24 max-w-full object-contain" alt="Logo de la empresa" />
              <img v-else src="/brand/logo-full.png" class="max-h-24 max-w-full object-contain opacity-70" alt="Logo por defecto" />
            </div>
            <div v-if="canEdit" class="flex items-center gap-2">
              <label class="btn-secondary cursor-pointer">
                {{ uploadingKind === 'full' ? 'Subiendo…' : 'Cambiar logo' }}
                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoSelected('full', $event)" />
              </label>
              <button v-if="form['company.logo_full_path']" type="button" class="btn-secondary !text-red-600" @click="removeLogo('full')">Quitar</button>
            </div>
          </div>
          <p class="mt-3 text-xs text-gray-400">Formatos: PNG, JPG o WEBP (máx. 5 MB). El cambio se refleja al recargar (Ctrl+F5).</p>
        </section>

        <!-- Cuentas bancarias (salen en el pie del comprobante) -->
        <section class="card">
          <h2 class="mb-1 text-sm font-bold uppercase tracking-wide text-gray-500">Cuentas bancarias</h2>
          <p class="mb-3 text-xs text-gray-400">Aparecen en el pie de los comprobantes (boleta/factura). Deja el nombre vacío para no mostrar esa cuenta.</p>
          <div v-for="n in [1, 2]" :key="n" class="mb-3 grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-3">
            <FormField :label="`Banco ${n}`">
              <input v-model="form[`company.bank${n}_name`]" class="form-input" :disabled="!canEdit" placeholder="BCP, BBVA…" maxlength="40" />
            </FormField>
            <FormField label="N° de cuenta">
              <input v-model="form[`company.bank${n}_account`]" class="form-input" :disabled="!canEdit" maxlength="40" />
            </FormField>
            <FormField label="CCI (interbancario)">
              <input v-model="form[`company.bank${n}_cci`]" class="form-input" :disabled="!canEdit" maxlength="40" />
            </FormField>
          </div>
        </section>

        <!-- Parámetros -->
        <section class="card">
          <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-500">Parámetros del sistema</h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="IGV (%)">
              <input v-model="form['tax.igv_rate']" type="number" step="0.1" min="0" max="30" class="form-input" :disabled="!canEdit" />
            </FormField>
            <FormField label="Vigencia de reservas (días)">
              <input v-model="form['sales.reservation_days']" type="number" min="0" class="form-input" :disabled="!canEdit" />
            </FormField>
          </div>
          <p class="mt-2 text-xs text-gray-500">
            El IGV se aplica a las nuevas ventas y compras. Los comprobantes ya emitidos no cambian (§19).
          </p>
        </section>

        <!-- Tipo de cambio del día -->
        <section class="card">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Tipo de cambio del día (SUNAT)</h2>
            <button type="button" class="btn-secondary !py-1 !text-xs" :disabled="exLoading" @click="loadRate">
              {{ exLoading ? 'Consultando…' : 'Actualizar de SUNAT' }}
            </button>
          </div>
          <div class="flex flex-wrap items-end gap-6">
            <div class="flex gap-6">
              <div>
                <p class="text-xs font-medium uppercase text-gray-400">Compra</p>
                <p class="text-2xl font-bold text-gray-700">S/ {{ ex.buy ? Number(ex.buy).toFixed(3) : '—' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium uppercase text-gray-400">Venta</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ ex.sell ? Number(ex.sell).toFixed(3) : '—' }}</p>
              </div>
            </div>
            <div class="text-xs text-gray-500">
              por US$ 1<template v-if="ex.date"> · {{ ex.date }}</template><br />
              <span
                class="font-medium"
                :class="ex.source === 'sunat' || ex.source === 'guardado' ? 'text-green-600' : ex.stale ? 'text-amber-600' : 'text-gray-400'"
              >
                {{ ex.source === 'sunat' ? 'SUNAT hoy' : ex.source === 'guardado' ? 'guardado hoy' : ex.source === 'manual' ? 'manual' : ex.stale ? 'no es de hoy — revisar' : 'sin datos' }}
              </span>
            </div>
          </div>
          <div v-if="canEdit" class="mt-3 flex flex-wrap items-end gap-3">
            <FormField label="Compra (S/)">
              <input v-model.number="exManualBuy" type="number" step="0.001" min="0" class="form-input w-28" />
            </FormField>
            <FormField label="Venta (S/)">
              <input v-model.number="exManualSell" type="number" step="0.001" min="0" class="form-input w-28" />
            </FormField>
            <button type="button" class="btn-secondary" :disabled="exLoading" @click="saveRate">Guardar manual</button>
          </div>
          <p class="mt-2 text-xs text-gray-400">
            Se usa para convertir compras/ventas en dólares a soles. Se intenta traer de SUNAT automáticamente; si no está disponible, ingrésalo manual.
          </p>
        </section>

        <p v-if="message" class="text-sm text-green-700">{{ message }}</p>
        <p v-if="errorMsg" class="text-sm text-red-600">{{ errorMsg }}</p>

        <div v-if="canEdit" class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Guardando…' : 'Guardar perfil' }}
          </button>
        </div>
      </form>
    </div>
  </DefaultLayout>
</template>
