<script setup lang="ts">
defineProps<{
  active: boolean
  activeLabel?: string
  inactiveLabel?: string
}>()
</script>

<template>
  <span class="chip" :class="active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
    {{ active ? (activeLabel ?? 'Activo') : (inactiveLabel ?? 'Inactivo') }}
  </span>
</template>
