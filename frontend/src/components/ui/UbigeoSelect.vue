<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { ubigeoService } from '@/services/ubigeo'
import type { UbigeoItem } from '@/types/ubigeo'

/**
 * Selector en cascada Departamento → Provincia → Distrito (ubigeo Perú).
 * Trabaja con los NOMBRES (los campos del cliente son texto). Reutilizable.
 */
const props = defineProps<{
  department: string | null
  province: string | null
  district: string | null
}>()

const emit = defineEmits<{
  (e: 'update:department', v: string | null): void
  (e: 'update:province', v: string | null): void
  (e: 'update:district', v: string | null): void
}>()

const departments = ref<UbigeoItem[]>([])
const provinces = ref<UbigeoItem[]>([])
const districts = ref<UbigeoItem[]>([])
const depId = ref('')
const provId = ref('')
const distId = ref('')

/** Normaliza para comparar nombres (ignora acentos, mayúsculas y separadores). */
const norm = (s: string): string =>
  s
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .replace(/[^a-z0-9]+/gi, ' ')
    .trim()
    .toLowerCase()

async function loadProvinces(): Promise<void> {
  provinces.value = depId.value ? await ubigeoService.provinces(depId.value) : []
}
async function loadDistricts(): Promise<void> {
  districts.value = provId.value ? await ubigeoService.districts(provId.value) : []
}

async function onDepartment(): Promise<void> {
  emit('update:department', departments.value.find((d) => d.id === depId.value)?.name ?? null)
  provId.value = ''
  distId.value = ''
  emit('update:province', null)
  emit('update:district', null)
  await loadProvinces()
  districts.value = []
}
async function onProvince(): Promise<void> {
  emit('update:province', provinces.value.find((p) => p.id === provId.value)?.name ?? null)
  distId.value = ''
  emit('update:district', null)
  await loadDistricts()
}
function onDistrict(): void {
  emit('update:district', districts.value.find((d) => d.id === distId.value)?.name ?? null)
}

/**
 * Empareja los nombres recibidos (props) con las opciones y selecciona en
 * cascada departamento → provincia → distrito. Se usa al montar Y cada vez que
 * cambian los props (ej. cuando una consulta RUC autocompleta esos campos).
 * No emite: solo ajusta la selección interna, así no genera bucles.
 */
async function syncFromProps(): Promise<void> {
  if (departments.value.length === 0) {
    departments.value = await ubigeoService.departments()
  }
  const dep = props.department ? departments.value.find((d) => norm(d.name) === norm(props.department!)) : undefined
  depId.value = dep?.id ?? ''
  provinces.value = dep ? await ubigeoService.provinces(dep.id) : []

  const prov = props.province ? provinces.value.find((p) => norm(p.name) === norm(props.province!)) : undefined
  provId.value = prov?.id ?? ''
  districts.value = prov ? await ubigeoService.districts(prov.id) : []

  const dist = props.district ? districts.value.find((d) => norm(d.name) === norm(props.district!)) : undefined
  distId.value = dist?.id ?? ''
}

onMounted(() => {
  void syncFromProps()
})

// Reacciona cuando el RUC (u otra fuente) rellena/cambia el ubigeo después del montaje.
watch(
  () => `${props.department ?? ''}|${props.province ?? ''}|${props.district ?? ''}`,
  () => {
    void syncFromProps()
  },
)
</script>

<template>
  <div class="grid grid-cols-3 gap-4">
    <div>
      <label class="form-label">Departamento</label>
      <select v-model="depId" class="form-input" @change="onDepartment">
        <option value="">—</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
    </div>
    <div>
      <label class="form-label">Provincia</label>
      <select v-model="provId" class="form-input" :disabled="!depId" @change="onProvince">
        <option value="">—</option>
        <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>
    <div>
      <label class="form-label">Distrito</label>
      <select v-model="distId" class="form-input" :disabled="!provId" @change="onDistrict">
        <option value="">—</option>
        <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
    </div>
  </div>
</template>
