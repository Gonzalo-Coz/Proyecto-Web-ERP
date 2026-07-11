<script setup lang="ts">
import { ref, watch } from 'vue'
import type { PageMeta, TableColumn } from '@/types/common'

/**
 * Tabla estándar del ERP (§5): búsqueda rápida, ordenamiento por columnas
 * y paginación de servidor. Reutilizada por todos los módulos.
 */
const props = defineProps<{
  columns: TableColumn[]
  rows: Record<string, unknown>[]
  meta: PageMeta | null
  loading?: boolean
  searchPlaceholder?: string
}>()

const emit = defineEmits<{
  (e: 'change', payload: { page: number; search: string; sort: string; direction: 'asc' | 'desc' }): void
}>()

const search = ref('')
const sort = ref('')
const direction = ref<'asc' | 'desc'>('asc')
let debounceTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => notify(1), 300)
})

function notify(page: number): void {
  emit('change', { page, search: search.value, sort: sort.value, direction: direction.value })
}

function toggleSort(column: TableColumn): void {
  if (!column.sortable) return
  if (sort.value === column.key) {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = column.key
    direction.value = 'asc'
  }
  notify(1)
}

function goTo(page: number): void {
  if (!props.meta || page < 1 || page > props.meta.totalPages) return
  notify(page)
}
</script>

<template>
  <div class="card p-0">
    <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4">
      <input
        v-model="search"
        type="search"
        class="form-input max-w-xs"
        :placeholder="searchPlaceholder ?? 'Buscar…'"
      />
      <slot name="toolbar" />
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              class="px-4 py-3 font-medium"
              :class="{ 'cursor-pointer select-none hover:text-gray-800': column.sortable }"
              @click="toggleSort(column)"
            >
              {{ column.label }}
              <span v-if="sort === column.key">{{ direction === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th v-if="$slots.actions" class="px-4 py-3 text-right font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-gray-400">
              Cargando…
            </td>
          </tr>
          <tr v-else-if="rows.length === 0">
            <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-gray-400">
              Sin resultados.
            </td>
          </tr>
          <tr
            v-for="row in rows"
            v-else
            :key="String(row.id)"
            class="border-t border-gray-100 hover:bg-gray-50"
          >
            <td v-for="column in columns" :key="column.key" class="px-4 py-3">
              <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
            </td>
            <td v-if="$slots.actions" class="px-4 py-3 text-right">
              <slot name="actions" :row="row" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta && meta.totalPages > 1"
      class="flex items-center justify-between border-t border-gray-200 p-4 text-sm text-gray-600"
    >
      <span>{{ meta.total }} registro(s) — página {{ meta.page }} de {{ meta.totalPages }}</span>
      <div class="flex gap-2">
        <button class="btn-secondary" :disabled="meta.page <= 1" @click="goTo(meta.page - 1)">
          Anterior
        </button>
        <button
          class="btn-secondary"
          :disabled="meta.page >= meta.totalPages"
          @click="goTo(meta.page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>
  </div>
</template>
