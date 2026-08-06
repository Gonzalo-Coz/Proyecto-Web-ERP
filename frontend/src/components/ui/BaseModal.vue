<script setup lang="ts">
withDefaults(
  defineProps<{
    open: boolean
    title: string
    /** Amplitud de la ventana: md (formularios simples), lg (estándar), xl (detalle con líneas). */
    size?: 'md' | 'lg' | 'xl'
  }>(),
  { size: 'lg' },
)

const emit = defineEmits<{ (e: 'close'): void }>()

const SIZES: Record<string, string> = {
  md: 'max-w-xl',
  lg: 'max-w-3xl',
  xl: 'max-w-5xl',
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8">
      <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px]" @click="emit('close')" />
      <div
        class="relative z-10 my-auto w-full rounded-2xl border border-slate-200 bg-white shadow-2xl"
        :class="SIZES[size]"
      >
        <div class="flex items-center justify-between rounded-t-2xl border-b border-slate-200 bg-slate-50 px-6 py-4">
          <h2 class="text-base font-semibold tracking-tight text-slate-800">{{ title }}</h2>
          <button
            type="button"
            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700"
            aria-label="Cerrar"
            @click="emit('close')"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="max-h-[75vh] overflow-y-auto px-6 py-5">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
