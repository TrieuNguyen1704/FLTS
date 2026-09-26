<script setup>
import { useRouter } from 'vue-router'
import { authStore } from '../stores/auth'
import { toast } from '../stores/toast'

const router = useRouter()
async function signOut() {
  await authStore.logout()
  toast.show('You have been signed out.')
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="topbar">
    <div><p class="topbar__eyebrow">FLIPPED LEARNING TOOL SUPPORT</p><p class="topbar__scope">Sprint 1 progress demo · 28 September 2026</p></div>
    <div class="topbar__account">
      <div class="avatar">{{ authStore.user.value?.name?.slice(0, 1) || 'U' }}</div>
      <div class="topbar__identity"><strong>{{ authStore.user.value?.name }}</strong><span>{{ authStore.user.value?.role }}</span></div>
      <button class="text-button" @click="signOut">Sign out</button>
    </div>
  </header>
</template>
