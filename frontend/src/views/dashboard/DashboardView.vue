<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

/** Navega al destino de una alerta (si lo tiene). */
function goToAlert(a: { link?: { name: string; query?: Record<string, string> } }): void {
  if (a.link?.name) router.push({ name: a.link.name, query: a.link.query ?? {} })
}
const data = ref<any>(null)
const loading = ref(true)
const forbidden = ref(false)

onMounted(async () => {
  if (!auth.can('dashboard.main.view')) {
    forbidden.value = true
    loading.value = false
    return
  }
  try {
    data.value = (await api.get('/dashboard')).data
  } catch {
    forbidden.value = true
  } finally {
    loading.value = false
  }
})

function money(v: string | number | null | undefined): string {
  return `S/ ${Number(v ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

// ---- Gráfico 1: tendencia de ventas (6 meses) ----
const trend = computed<{ label: string; total: string }[]>(() => data.value?.sales?.trend ?? [])
const trendMax = computed(() => Math.max(...trend.value.map((t) => Number(t.total)), 1))
const barPct = (t: { total: string }): number => Math.round((Number(t.total) / trendMax.value) * 100)

// ---- Gráfico 2: cobranza ----
const billed = computed(() => Number(data.value?.sales?.totalBilled ?? 0))
const collected = computed(() => Number(data.value?.sales?.totalCollected ?? 0))
const collectedPct = computed(() => (billed.value > 0 ? Math.min(100, Math.round((collected.value / billed.value) * 100)) : 0))

// ---- Rankings como barras de progreso ----
function pctOf(value: number, list: { total?: number | string; sold?: number | string }[], key: 'total' | 'sold'): number {
  const max = Math.max(...list.map((x) => Number(x[key] ?? 0)), 1)
  return Math.round((value / max) * 100)
}

const kpis = computed(() => [
  { label: 'Ventas hoy', value: data.value?.sales?.today, accent: 'text-primary-800' },
  { label: 'Esta semana', value: data.value?.sales?.week, accent: 'text-primary-800' },
  { label: 'Este mes', value: data.value?.sales?.month, accent: 'text-primary-800' },
  { label: 'Este año', value: data.value?.sales?.year, accent: 'text-primary-800' },
])
</script>

<template>
  <DefaultLayout>
    <p v-if="loading" class="py-12 text-center text-slate-400">Cargando indicadores…</p>
    <p v-else-if="forbidden" class="py-12 text-center text-slate-400">
      Bienvenido a YIGM ERP. No tienes permisos para ver los indicadores gerenciales.
    </p>

    <div v-else-if="data" class="space-y-6">
      <!-- Alertas -->
      <div v-if="data.alerts.length" class="space-y-2">
        <div
          v-for="(a, i) in data.alerts"
          :key="i"
          class="flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-sm"
          :class="[
            a.level === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : a.level === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-primary-200 bg-primary-50 text-primary-800',
            a.link ? 'cursor-pointer transition hover:brightness-95' : '',
          ]"
          :role="a.link ? 'button' : undefined"
          @click="goToAlert(a)"
        >
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
          </svg>
          <span class="flex-1">{{ a.message }}</span>
          <svg v-if="a.link" class="h-4 w-4 shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </div>

      <!-- KPIs de ventas -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div v-for="k in kpis" :key="k.label" class="card">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ k.label }}</p>
          <p class="mt-1.5 text-2xl font-bold tabular-nums" :class="k.accent">{{ money(k.value) }}</p>
        </div>
      </div>

      <!-- ==== Los 2 gráficos ==== -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Gráfico 1: tendencia de ventas (barras) -->
        <div class="card lg:col-span-2">
          <div class="mb-4 flex items-baseline justify-between">
            <h3 class="text-sm font-bold text-slate-700">Ventas de los últimos 6 meses</h3>
            <span class="text-xs text-slate-400">Completadas</span>
          </div>
          <div class="flex h-44 items-end gap-3">
            <div v-for="(t, i) in trend" :key="i" class="group flex flex-1 flex-col items-center gap-2">
              <div class="relative flex w-full flex-1 items-end">
                <div
                  class="w-full rounded-t-md bg-gradient-to-t from-primary-700 to-primary-400 transition-all duration-500 group-hover:from-primary-800 group-hover:to-primary-500"
                  :style="{ height: `max(4px, ${barPct(t)}%)` }"
                >
                  <span class="pointer-events-none absolute -top-5 left-1/2 -translate-x-1/2 whitespace-nowrap text-[10px] font-semibold text-slate-500 opacity-0 transition group-hover:opacity-100">
                    {{ money(t.total) }}
                  </span>
                </div>
              </div>
              <span class="text-xs font-medium text-slate-400">{{ t.label }}</span>
            </div>
          </div>
        </div>

        <!-- Gráfico 2: cobranza (progreso) -->
        <div class="card flex flex-col">
          <h3 class="mb-4 text-sm font-bold text-slate-700">Cobranza acumulada</h3>
          <div class="flex flex-1 flex-col justify-center">
            <div class="mb-2 flex items-baseline justify-between">
              <span class="text-2xl font-bold tabular-nums text-emerald-600">{{ collectedPct }}%</span>
              <span class="text-xs text-slate-400">cobrado</span>
            </div>
            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-700" :style="{ width: `${collectedPct}%` }" />
            </div>
            <dl class="mt-5 space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500">Facturado</dt><dd class="font-semibold tabular-nums text-slate-700">{{ money(billed) }}</dd></div>
              <div class="flex justify-between"><dt class="text-slate-500">Cobrado</dt><dd class="font-semibold tabular-nums text-emerald-600">{{ money(collected) }}</dd></div>
              <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="text-slate-500">Por cobrar</dt><dd class="font-semibold tabular-nums text-red-600">{{ money(data.sales.receivables) }}</dd></div>
            </dl>
          </div>
        </div>
      </div>

      <!-- Caja / Inventario / Taller -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card">
          <h3 class="mb-3 text-sm font-bold text-slate-700">Caja</h3>
          <div class="space-y-1.5 text-sm">
            <p class="flex justify-between"><span class="text-slate-500">Estado</span><strong :class="data.cash.isOpen ? 'text-emerald-600' : 'text-slate-400'">{{ data.cash.isOpen ? `Abierta (${data.cash.sessionNumber})` : 'Cerrada' }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Ingresos hoy</span><strong class="tabular-nums text-emerald-600">{{ money(data.cash.todayIncome) }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Egresos hoy</span><strong class="tabular-nums text-red-600">{{ money(data.cash.todayExpense) }}</strong></p>
            <p class="flex justify-between border-t border-slate-100 pt-1.5"><span class="text-slate-500">Neto del día</span><strong class="tabular-nums text-slate-800">{{ money(data.cash.todayNet) }}</strong></p>
          </div>
        </div>
        <div class="card">
          <h3 class="mb-3 text-sm font-bold text-slate-700">Inventario</h3>
          <div class="space-y-1.5 text-sm">
            <p class="flex justify-between"><span class="text-slate-500">Motos disponibles</span><strong class="text-slate-800">{{ data.inventory.motorcyclesAvailable }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Repuestos activos</span><strong class="text-slate-800">{{ data.inventory.sparePartsActive }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Stock bajo</span><strong class="text-amber-600">{{ data.inventory.lowStock }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Sin stock</span><strong class="text-red-600">{{ data.inventory.outOfStock }}</strong></p>
          </div>
        </div>
        <div class="card">
          <h3 class="mb-3 text-sm font-bold text-slate-700">Taller</h3>
          <div class="space-y-1.5 text-sm">
            <p class="flex justify-between"><span class="text-slate-500">Pendientes</span><strong class="text-slate-800">{{ data.workshop.pending }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">En proceso</span><strong class="text-slate-800">{{ data.workshop.inProgress }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Listas para entrega</span><strong class="text-emerald-600">{{ data.workshop.ready }}</strong></p>
            <p class="flex justify-between"><span class="text-slate-500">Retrasadas</span><strong class="text-red-600">{{ data.workshop.delayed }}</strong></p>
          </div>
        </div>
      </div>

      <!-- Rankings como barras de progreso -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card">
          <h3 class="mb-3 text-sm font-bold text-slate-700">Ventas por vendedor (mes)</h3>
          <p v-if="!data.sales.bySeller.length" class="text-sm text-slate-400">Sin ventas este mes.</p>
          <div v-for="s in data.sales.bySeller" :key="s.seller" class="mb-2.5">
            <div class="mb-1 flex justify-between text-sm">
              <span class="truncate text-slate-600">{{ s.seller }} <span class="text-xs text-slate-400">({{ s.count }})</span></span>
              <strong class="tabular-nums text-slate-700">{{ money(s.total) }}</strong>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-primary-500" :style="{ width: `${pctOf(Number(s.total), data.sales.bySeller, 'total')}%` }" />
            </div>
          </div>
        </div>

        <div class="card">
          <h3 class="mb-3 text-sm font-bold text-slate-700">Repuestos más vendidos</h3>
          <p v-if="!data.inventory.topSold.length" class="text-sm text-slate-400">Sin ventas registradas.</p>
          <div v-for="p in data.inventory.topSold" :key="p.description" class="mb-2.5">
            <div class="mb-1 flex justify-between text-sm">
              <span class="truncate text-slate-600">{{ p.description }}</span>
              <strong class="tabular-nums text-slate-700">{{ p.sold }}</strong>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-accent/70" :style="{ width: `${pctOf(Number(p.sold), data.inventory.topSold, 'sold')}%` }" />
            </div>
          </div>
        </div>

        <div class="card">
          <h3 class="mb-3 text-sm font-bold text-slate-700">Mejores clientes</h3>
          <p v-if="!data.customers.topBuyers.length" class="text-sm text-slate-400">Sin datos.</p>
          <div v-for="c in data.customers.topBuyers" :key="c.name" class="mb-2.5">
            <div class="mb-1 flex justify-between text-sm">
              <span class="truncate text-slate-600">{{ c.name }}</span>
              <strong class="tabular-nums text-slate-700">{{ money(c.total) }}</strong>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-primary-400" :style="{ width: `${pctOf(Number(c.total), data.customers.topBuyers, 'total')}%` }" />
            </div>
          </div>
          <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-500">
            {{ data.customers.total }} clientes activos · {{ data.customers.newThisMonth }} nuevos este mes ·
            Compras del mes: {{ money(data.purchases.month) }}
          </p>
        </div>
      </div>

      <!-- Actividad reciente -->
      <div class="card">
        <h3 class="mb-3 text-sm font-bold text-slate-700">Actividad reciente</h3>
        <p v-if="!data.recentActivity.length" class="text-sm text-slate-400">Sin actividad registrada.</p>
        <table v-else class="w-full text-left text-sm">
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(a, i) in data.recentActivity" :key="i">
              <td class="py-1.5 text-xs text-slate-400">{{ new Date(a.created_at).toLocaleString('es-PE') }}</td>
              <td class="py-1.5 text-slate-700">{{ a.username ?? 'sistema' }}</td>
              <td class="py-1.5 text-xs uppercase text-slate-400">{{ a.module }}</td>
              <td class="py-1.5 text-slate-600">{{ a.action === 'create' ? 'Registró' : a.action === 'update' ? 'Modificó' : 'Eliminó' }} {{ a.entity_class.split('\\').pop() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </DefaultLayout>
</template>
