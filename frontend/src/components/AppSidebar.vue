<script setup>
import { computed } from 'vue'
import { authStore } from '../stores/auth'

const navigation = computed(() => {
  if (authStore.role.value === 'student') {
    return [{ label: 'Khóa học của tôi', to: { name: 'student-dashboard' } }]
  }
  if (authStore.role.value === 'lecturer') {
    return [
      { label: 'Tổng quan', to: { name: 'lecturer-dashboard' } },
      { label: 'Khóa học', to: { name: 'course-management' } },
    ]
  }
  if (authStore.role.value === 'admin') {
    return [{ label: 'Tài khoản', to: { name: 'admin-dashboard' } }]
  }
  return []
})
</script>

<template>
  <aside class="sidebar">
    <RouterLink
      class="brand"
      :to="authStore.role.value === 'student'
        ? { name: 'student-dashboard' }
        : authStore.role.value === 'lecturer'
          ? { name: 'lecturer-dashboard' }
          : authStore.role.value === 'admin'
            ? { name: 'admin-dashboard' }
            : { name: 'access-unavailable' }"
    >
      <span class="brand__mark">F</span><span>FLTS</span>
    </RouterLink>
    <p class="sidebar__caption">MENU</p>
    <nav class="sidebar__nav" aria-label="Menu chính">
      <RouterLink v-for="item in navigation" :key="item.label" :to="item.to" class="sidebar__link">
        {{ item.label }}
      </RouterLink>
    </nav>
  </aside>
</template>
