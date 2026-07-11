<script setup lang="ts">
import BaseModal from '@/components/ui/BaseModal.vue'

defineProps<{
  open: boolean
  title: string
  message: string
  confirmLabel?: string
  danger?: boolean
}>()

const emit = defineEmits<{ (e: 'confirm'): void; (e: 'cancel'): void }>()
</script>

<template>
  <BaseModal :open="open" :title="title" @close="emit('cancel')">
    <p class="text-sm text-gray-600">{{ message }}</p>
    <div class="mt-6 flex justify-end gap-3">
      <button type="button" class="btn-secondary" @click="emit('cancel')">Cancelar</button>
      <button
        type="button"
        class="btn-primary"
        :class="{ '!bg-red-600 hover:!bg-red-700 focus:!ring-red-500': danger }"
        @click="emit('confirm')"
      >
        {{ confirmLabel ?? 'Confirmar' }}
      </button>
    </div>
  </BaseModal>
</template>
