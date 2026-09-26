<script setup>
import { RouterView, useRoute } from 'vue-router'
import AppShell from './layouts/AppShell.vue'
import AppToast from './components/AppToast.vue'

const route = useRoute()
</script>

<template>
  <RouterView v-slot="{ Component }">
    <AppShell v-if="route.meta.requiresAuth">
      <component :is="Component" />
    </AppShell>
    <component :is="Component" v-else />
  </RouterView>
  <AppToast />
</template>
