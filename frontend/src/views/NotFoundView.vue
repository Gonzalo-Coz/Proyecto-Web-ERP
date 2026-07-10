<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-gray-100">
    <h1 class="text-6xl font-bold text-primary-600">404</h1>
    <p class="text-gray-600">La página solicitada no existe.</p>
    <RouterLink :to="{ name: 'dashboard' }" class="btn-primary">Ir al Dashboard</RouterLink>
  </div>
</template>
