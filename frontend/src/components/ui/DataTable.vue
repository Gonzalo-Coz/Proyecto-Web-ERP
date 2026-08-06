<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { PageMeta, TableColumn } from '@/types/common'

/**
 * Tabla estándar del ERP: búsqueda, ordenamiento y paginación de servidor.
 * API idéntica a v1 (props/emits); solo evoluciona la presentación.
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

/** "Mostrando 11–20 de 45" — contexto que la paginación simple no da. */
const rangeLabel = computed(() => {
  if (!props.meta || props.meta.total === 0) return ''
  const from = (props.meta.page - 1) * props.meta.perPage + 1
  const to = Math.min(props.meta.page * props.meta.perPage, props.meta.total)
  return `Mostrando ${from}–${to} de ${props.meta.total}`
})
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-card">
    <!-- Barra de herramientas -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/60 px-4 py-3">
      <div class="relative">
        <svg
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
          fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
        </svg>
        <input
          v-model="search"
          type="search"
          class="form-input w-72 !pl-9"
          :placeholder="searchPlaceholder ?? 'Buscar…'"
        />
      </div>
      <slot name="toolbar" />
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-200">
            <th
              v-for="column in columns"
              :key="column.key"
              class="px-4 py-3"
              :class="{ 'cursor-pointer select-none hover:text-primary-700': column.sortable }"
              @click="toggleSort(column)"
            >
              <span class="inline-flex items-center gap-1">
                {{ column.label }}
                <svg
                  v-if="column.sortable"
                  class="h-3 w-3 transition"
                  :class="sort === column.key ? 'text-primary-600' : 'text-slate-300'"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                >
                  <path
                    stroke-linecap="round" stroke-linejoin="round"
                    :d="sort === column.key && direction === 'desc' ? 'M19 9l-7 7-7-7' : 'M5 15l7-7 7 7'"
                  />
                </svg>
              </span>
            </th>
            <th v-if="$slots.actions" class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-slate-400">
              <span class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                Cargando…
              </span>
            </td>
          </tr>
          <tr v-else-if="rows.length === 0">
            <td :colspan="columns.length + 1" class="px-4 py-14 text-center">
              <svg class="mx-auto mb-3 h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
              </svg>
              <p class="text-sm font-medium text-slate-500">Sin resultados</p>
              <p class="text-xs text-slate-400">Ajusta la búsqueda o registra un nuevo elemento.</p>
            </td>
          </tr>
          <tr v-for="row in rows" v-else :key="String(row.id)">
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
      v-if="meta && meta.total > 0"
      class="flex items-center justify-between border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-sm text-slate-500"
    >
      <span class="tabular-nums">{{ rangeLabel }}</span>
      <div v-if="meta.totalPages > 1" class="flex items-center gap-2">
        <button class="btn-ghost !px-2.5" :disabled="meta.page <= 1" @click="goTo(meta.page - 1)">‹ Anterior</button>
        <span class="tabular-nums text-xs">Página {{ meta.page }} / {{ meta.totalPages }}</span>
        <button class="btn-ghost !px-2.5" :disabled="meta.page >= meta.totalPages" @click="goTo(meta.page + 1)">Siguiente ›</button>
      </div>
    </div>
  </div>
</template>
