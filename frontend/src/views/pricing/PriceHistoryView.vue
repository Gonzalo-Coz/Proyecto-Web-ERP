<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { pricingService } from '@/services/pricing'
import type { PageMeta, TableColumn } from '@/types/common'
import type { PriceHistoryItem } from '@/types/pricing'

const route = useRoute()

const columns: TableColumn[] = [
  { key: 'createdAt', label: 'Fecha' },
  { key: 'subjectTypeLabel', label: 'Tipo' },
  { key: 'subjectLabel', label: 'Elemento' },
  { key: 'oldPrice', label: 'Precio anterior' },
  { key: 'newPrice', label: 'Precio nuevo' },
  { key: 'reason', label: 'Motivo' },
  { key: 'username', label: 'Usuario' },
]

const rows = ref<PriceHistoryItem[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(false)

const query = reactive({
  page: 1,
  perPage: 10,
  search: '',
  subjectType: (route.query.subjectType as string) ?? '',
  subjectId: route.query.subjectId ? Number(route.query.subjectId) : undefined,
  from: '',
  to: '',
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const result = await pricingService.history({
      page: query.page,
      perPage: query.perPage,
      search: query.search || undefined,
      subjectType: query.subjectType || undefined,
      subjectId: query.subjectId,
      from: query.from || undefined,
      to: query.to || undefined,
    })
    rows.value = result.data
    meta.value = result.meta
  } finally {
    loading.value = false
  }
}

function onTableChange(p: { page: number; search: string }): void {
  query.page = p.page
  query.search = p.search
  load()
}

function applyFilters(): void {
  query.page = 1
  load()
}

const money = (v: string | null): string => (v === null ? '—' : `S/ ${Number(v).toFixed(2)}`)
const fmtDate = (v: string): string =>
  new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(v))

/** Variación porcentual entre precio anterior y nuevo (para lectura rápida). */
function deltaPct(item: PriceHistoryItem): number | null {
  if (item.oldPrice === null || item.newPrice === null || Number(item.oldPrice) === 0) return null
  return ((Number(item.newPrice) - Number(item.oldPrice)) / Number(item.oldPrice)) * 100
}

onMounted(load)
</script>

<template>
  <DefaultLayout>
    <div class="mb-6">
      <h2 class="text-xl font-bold tracking-tight text-slate-900">Historial de Precios</h2>
      <p class="text-sm text-slate-500">Trazabilidad de cambios de precio de repuestos y modelos, con su motivo.</p>
    </div>

    <DataTable
      :columns="columns"
      :rows="rows as unknown as Record<string, unknown>[]"
      :meta="meta"
      :loading="loading"
      search-placeholder="Buscar por elemento, motivo o usuario…"
      @change="onTableChange"
    >
      <template #toolbar>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="query.subjectType" class="form-input w-44" @change="applyFilters">
            <option value="">Todos los tipos</option>
            <option value="spare_part">Repuestos</option>
            <option value="motorcycle_model">Modelos de moto</option>
          </select>
          <input v-model="query.from" type="date" class="form-input w-40" @change="applyFilters" />
          <input v-model="query.to" type="date" class="form-input w-40" @change="applyFilters" />
        </div>
      </template>

      <template #cell-createdAt="{ row }">
        <span class="whitespace-nowrap text-slate-600">{{ fmtDate((row as PriceHistoryItem).createdAt) }}</span>
      </template>
      <template #cell-oldPrice="{ row }">
        <span class="text-slate-500">{{ money((row as PriceHistoryItem).oldPrice) }}</span>
      </template>
      <template #cell-newPrice="{ row }">
        <span class="inline-flex items-center gap-2 font-semibold text-slate-800">
          {{ money((row as PriceHistoryItem).newPrice) }}
          <span
            v-if="deltaPct(row as PriceHistoryItem) !== null"
            class="text-xs font-medium"
            :class="deltaPct(row as PriceHistoryItem)! >= 0 ? 'text-emerald-600' : 'text-accent'"
          >
            {{ deltaPct(row as PriceHistoryItem)! >= 0 ? '▲' : '▼' }}
            {{ Math.abs(deltaPct(row as PriceHistoryItem)!).toFixed(1) }}%
          </span>
        </span>
      </template>
      <template #cell-reason="{ row }">
        <span class="text-slate-600">{{ (row as PriceHistoryItem).reason ?? '—' }}</span>
      </template>
      <template #cell-username="{ row }">
        <span class="text-slate-500">{{ (row as PriceHistoryItem).username ?? 'sistema' }}</span>
      </template>
    </DataTable>
  </DefaultLayout>
</template>
