<script setup lang="ts">
import { onMounted, ref } from 'vue'
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

/** Normaliza para comparar nombres (ignora acentos y mayúsculas). */
const norm = (s: string): string =>
  s.normalize('NFD').replace(/\p{Diacritic}/gu, '').trim().toLowerCase()

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

onMounted(async () => {
  departments.value = await ubigeoService.departments()
  if (!props.department) return
  const dep = departments.value.find((d) => norm(d.name) === norm(props.department!))
  if (!dep) return
  depId.value = dep.id
  await loadProvinces()
  if (!props.province) return
  const prov = provinces.value.find((p) => norm(p.name) === norm(props.province!))
  if (!prov) return
  provId.value = prov.id
  await loadDistricts()
  if (!props.district) return
  const dist = districts.value.find((d) => norm(d.name) === norm(props.district!))
  if (dist) distId.value = dist.id
})
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
