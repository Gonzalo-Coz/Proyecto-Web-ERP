<script setup lang="ts">
// Punto de entrada: el layout lo decide cada ruta (meta.layout).
// ToastHost es global (CONS-004): notificaciones unificadas en todo el ERP.
import ToastHost from '@/components/ui/ToastHost.vue'
</script>

<template>
  <RouterView />
  <ToastHost />
</template>
