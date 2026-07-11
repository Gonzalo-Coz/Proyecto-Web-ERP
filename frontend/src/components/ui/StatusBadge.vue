<script setup lang="ts">
defineProps<{
  active: boolean
  activeLabel?: string
  inactiveLabel?: string
}>()
</script>

<template>
  <span
    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'"
  >
    {{ active ? (activeLabel ?? 'Activo') : (inactiveLabel ?? 'Inactivo') }}
  </span>
</template>
