<script setup>
import { computed } from 'vue'
import { authStore } from '../stores/auth'

const navigation = computed(() => {
  if (authStore.role.value === 'student') return [{ label: 'My learning', icon: '◈', to: { name: 'student-dashboard' } }]
  if (authStore.role.value === 'lecturer') return [
      { label: 'Overview', icon: '◈', to: { name: 'lecturer-dashboard' } },
      { label: 'Course management', icon: '▣', to: { name: 'course-management' } },
    ]
  return []
})
</script>

<template>
  <aside class="sidebar">
    <RouterLink class="brand" :to="authStore.role.value === 'student' ? { name: 'student-dashboard' } : authStore.role.value === 'lecturer' ? { name: 'lecturer-dashboard' } : { name: 'access-unavailable' }">
      <span class="brand__mark">F</span><span>FLTS</span>
    </RouterLink>
    <p class="sidebar__caption">SPRINT 1 DEMO</p>
    <nav class="sidebar__nav" aria-label="Primary navigation">
      <RouterLink v-for="item in navigation" :key="item.label" :to="item.to" class="sidebar__link">
        <span>{{ item.icon }}</span>{{ item.label }}
      </RouterLink>
    </nav>
    <div class="sidebar__note"><strong>Scope note</strong><span>Document storage only. Processing and RAG are not implemented.</span></div>
  </aside>
</template>
