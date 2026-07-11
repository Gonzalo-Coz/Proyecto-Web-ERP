<script setup lang="ts">
defineProps<{
  open: boolean
  title: string
}>()

const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/40" @click="emit('close')" />
      <div class="card relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
          <button
            type="button"
            class="text-gray-400 transition hover:text-gray-600"
            aria-label="Cerrar"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>
        <slot />
      </div>
    </div>
  </Teleport>
</template>
