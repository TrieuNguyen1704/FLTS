<script setup>
import AppSidebar from '../components/AppSidebar.vue'
import AppTopbar from '../components/AppTopbar.vue'
</script>

<template>
  <div class="app-shell">
    <AppSidebar />
    <div class="app-shell__content">
      <AppTopbar />
      <main class="page-content"><slot /></main>
    </div>
  </div>
</template>
