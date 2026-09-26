<script setup>
import { computed } from 'vue'
import { authStore } from '../stores/auth'

const navigation = computed(() => {
  if (authStore.role.value === 'student') return [{ label: 'My learning', icon: 'learning', to: { name: 'student-dashboard' } }]
  if (authStore.role.value === 'lecturer') return [
    { label: 'Overview', icon: 'overview', to: { name: 'lecturer-dashboard' } },
    { label: 'Course management', icon: 'courses', to: { name: 'course-management' } },
  ]
  if (authStore.role.value === 'admin') return [{ label: 'Account management', icon: 'accounts', to: { name: 'admin-dashboard' } }]
  return []
})
</script>

<template>
  <aside class="sidebar">
    <RouterLink class="brand" :to="authStore.role.value === 'student' ? { name: 'student-dashboard' } : authStore.role.value === 'lecturer' ? { name: 'lecturer-dashboard' } : authStore.role.value === 'admin' ? { name: 'admin-dashboard' } : { name: 'access-unavailable' }">
      <span class="brand__mark">F</span><span>FLTS</span>
    </RouterLink>
    <p class="sidebar__caption">SPRINT 1 DEMO</p>
    <nav class="sidebar__nav" aria-label="Primary navigation">
      <RouterLink v-for="item in navigation" :key="item.label" :to="item.to" class="sidebar__link">
        <!-- CSS icons avoid mojibake when the app is opened under a different Windows encoding. -->
        <span :class="['sidebar__icon', `sidebar__icon--${item.icon}`]" aria-hidden="true" />{{ item.label }}
      </RouterLink>
    </nav>
    <div class="sidebar__note"><strong>Scope note</strong><span>Document storage only. Processing and RAG are not implemented.</span></div>
  </aside>
</template>
