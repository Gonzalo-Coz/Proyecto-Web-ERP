<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const REPORT_TYPES = [
  { key: 'sales', label: 'Ventas' },
  { key: 'repuestosyamaha', label: 'Venta de Repuestos (Formato Yamaha)' },
  { key: 'motosyamaha', label: 'Venta de Motos (Formato Yamaha)' },
  { key: 'stockventasmotos', label: 'Stock y Ventas Motos (Formato Yamaha)' },
  { key: 'purchases', label: 'Compras' },
  { key: 'cash', label: 'Caja' },
  { key: 'kardex', label: 'Kardex' },
  { key: 'workshop', label: 'Taller' },
  { key: 'documents', label: 'Comprobantes SUNAT' },
  { key: 'customers', label: 'Clientes' },
  { key: 'suppliers', label: 'Proveedores' },
  { key: 'motorcycles', label: 'Motocicletas' },
  { key: 'inventory', label: 'Inventario Valorizado' },
  { key: 'stock', label: 'Stock de Repuestos' },
  { key: 'reposicion', label: 'Repuestos por reponer (pedido)' },
  { key: 'stockmotos', label: 'Stock de Motos' },
  { key: 'utilities', label: 'Utilidades' },
  { key: 'audit', label: 'Auditoría' },
]

const filters = reactive({
  type: 'sales',
  from: new Date().toISOString().slice(0, 8) + '01',
  to: new Date().toISOString().slice(0, 10),
})

/** Reportes que son una "foto" del estado actual (stock): no usan rango de fechas. */
const usesDateRange = computed(() => filters.type !== 'stockventasmotos')

const report = ref<{ title: string; columns: { label: string }[]; rows: unknown[][] } | null>(null)
const loading = ref(false)
const errorMsg = ref('')

async function generate(): Promise<void> {
  loading.value = true
  errorMsg.value = ''
  try {
    report.value = (await api.get(`/reports/${filters.type}`, { params: { from: filters.from, to: filters.to } })).data
  } catch (e: any) {
    errorMsg.value = e.response?.data?.detail ?? 'No se pudo generar el reporte.'
    report.value = null
  } finally {
    loading.value = false
  }
}

/** Descarga el reporte de Stock y Ventas de Motos en la PLANTILLA oficial Yamaha (.xlsx con diseño y filtros). */
const downloadingYamaha = ref(false)
async function downloadYamaha(): Promise<void> {
  downloadingYamaha.value = true
  try {
    const res = await api.get('/reports/stock-ventas-motos.xlsx', { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = 'stock_y_ventas_motos.xlsx'
    link.click()
    URL.revokeObjectURL(link.href)
  } catch {
    errorMsg.value = 'No se pudo generar el formato Yamaha.'
  } finally {
    downloadingYamaha.value = false
  }
}

/** Exporta el reporte visible a Excel. Stock y Ventas de Motos usa la plantilla oficial Yamaha. */
function exportExcel(): void {
  if (filters.type === 'stockventasmotos') {
    void downloadYamaha()
    return
  }
  if (!report.value) return
  const esc = (v: unknown): string =>
    String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const head = '<tr>' + report.value.columns.map((c) => `<th>${esc(c.label)}</th>`).join('') + '</tr>'
  const body = report.value.rows.map((r) => '<tr>' + r.map((v) => `<td>${esc(v)}</td>`).join('') + '</tr>').join('')
  const html =
    '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head>' +
    `<body><table border="1">${head}${body}</table></body></html>`
  // BOM para que Excel reconozca UTF-8 (tildes y ñ)
  const blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `reporte_${filters.type}_${filters.from}_${filters.to}.xls`
  link.click()
  URL.revokeObjectURL(link.href)
}
</script>

<template>
  <DefaultLayout>
    <div class="card mb-6">
      <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-4">
        <div>
          <label class="form-label">Reporte</label>
          <select v-model="filters.type" class="form-input">
            <option v-for="t in REPORT_TYPES" :key="t.key" :value="t.key">{{ t.label }}</option>
          </select>
        </div>
        <div v-if="usesDateRange">
          <label class="form-label">Desde</label>
          <input v-model="filters.from" type="date" class="form-input" />
        </div>
        <div v-if="usesDateRange">
          <label class="form-label">Hasta</label>
          <input v-model="filters.to" type="date" class="form-input" />
        </div>
        <div class="flex gap-2">
          <button class="btn-primary flex-1" :disabled="loading" @click="generate">
            {{ loading ? 'Generando…' : 'Generar' }}
          </button>
          <button
            v-if="report && auth.can('reports.main.export')"
            class="btn-secondary"
            :disabled="downloadingYamaha"
            @click="exportExcel"
          >
            {{ downloadingYamaha ? 'Generando…' : 'Exportar Excel' }}
          </button>
        </div>
      </div>
      <p v-if="errorMsg" class="mt-3 text-sm text-red-600">{{ errorMsg }}</p>
    </div>

    <div v-if="report" class="card p-0">
      <h2 class="border-b border-gray-200 p-4 text-sm font-semibold text-gray-700">
        {{ report.title }}<template v-if="usesDateRange"> — {{ filters.from }} al {{ filters.to }}</template> ({{ report.rows.length }} registros)
      </h2>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th v-for="(c, i) in report.columns" :key="i" class="px-4 py-2 font-medium">{{ c.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="report.rows.length === 0">
              <td :colspan="report.columns.length" class="px-4 py-8 text-center text-gray-400">
                Sin datos en el rango seleccionado.
              </td>
            </tr>
            <tr v-for="(row, i) in report.rows" :key="i" class="border-t border-gray-100 hover:bg-gray-50">
              <td v-for="(cell, j) in row" :key="j" class="px-4 py-2">{{ cell ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <p v-else-if="!loading" class="py-8 text-center text-sm text-gray-400">
      {{ usesDateRange ? 'Selecciona un reporte y un rango de fechas, luego pulsa Generar.' : 'Pulsa Generar para ver el reporte de stock actual.' }}
    </p>
  </DefaultLayout>
</template>
