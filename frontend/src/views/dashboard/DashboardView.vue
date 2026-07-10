<script setup lang="ts">
import DefaultLayout from '@/layouts/DefaultLayout.vue'

// Placeholder: los indicadores reales (§6 del Documento Maestro) se
// implementarán de forma incremental conforme avancen las fases y se
// consolidarán en la Fase 9.
const indicators = [
  { label: 'Ventas del día', value: '—' },
  { label: 'Saldo de caja', value: '—' },
  { label: 'Motocicletas disponibles', value: '—' },
  { label: 'Órdenes de taller pendientes', value: '—' },
]
</script>

<template>
  <DefaultLayout>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="item in indicators" :key="item.label" class="card">
        <p class="text-sm text-gray-500">{{ item.label }}</p>
        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ item.value }}</p>
      </div>
    </div>

    <div class="card mt-6">
      <h2 class="text-sm font-medium text-gray-700">Actividad reciente</h2>
      <p class="mt-4 text-sm text-gray-400">
        Los indicadores y la actividad se activarán conforme se implementen los módulos.
      </p>
    </div>
  </DefaultLayout>
</template>
