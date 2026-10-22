<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'

/**
 * Lienzo genérico de Chart.js para el dashboard. Recibe tipo, datos y opciones;
 * se redibuja al cambiar los datos y se limpia al desmontar. Tipos laxos a
 * propósito (los datos se arman en el dashboard) para no acoplar al genérico.
 */
const props = defineProps<{
  type: string
  data: any
  options?: any
  height?: number
}>()

const el = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function render(): void {
  if (!el.value) return
  chart?.destroy()
  chart = new Chart(el.value, {
    type: props.type as any,
    data: props.data,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      ...(props.options ?? {}),
    },
  })
}

onMounted(render)
watch(() => [props.data, props.type], render, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
  <div :style="{ height: (height ?? 240) + 'px' }">
    <canvas ref="el"></canvas>
  </div>
</template>
