<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import type { ToastType } from '@/composables/useToast'

const { toasts, remove } = useToast()

const styles: Record<ToastType, { ring: string; icon: string; path: string }> = {
  success: {
    ring: 'border-emerald-200',
    icon: 'text-emerald-500',
    path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  },
  error: {
    ring: 'border-red-200',
    icon: 'text-red-500',
    path: 'M12 9v3m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z',
  },
  info: {
    ring: 'border-primary-200',
    icon: 'text-primary-500',
    path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  },
}
</script>

<template>
  <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-full max-w-sm flex-col gap-2">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-x-6 opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-6 opacity-0"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white px-4 py-3 shadow-overlay"
        :class="styles[t.type].ring"
        role="status"
      >
        <svg class="mt-0.5 h-5 w-5 shrink-0" :class="styles[t.type].icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="styles[t.type].path" />
        </svg>
        <p class="flex-1 text-sm text-slate-700">{{ t.message }}</p>
        <button type="button" class="text-slate-300 transition hover:text-slate-500" aria-label="Cerrar" @click="remove(t.id)">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
