<script setup>
import { computed } from 'vue'
import { authStore } from '../stores/auth'

const navigation = computed(() => {
  if (authStore.role.value === 'student') {
    return [{ label: 'Khóa học của tôi', icon: 'learning', to: { name: 'student-dashboard' } }]
  }
  if (authStore.role.value === 'lecturer') {
    return [
      { label: 'Tổng quan', icon: 'overview', to: { name: 'lecturer-dashboard' } },
      { label: 'Quản lý khóa học', icon: 'courses', to: { name: 'course-management' } },
    ]
  }
  if (authStore.role.value === 'admin') {
    return [{ label: 'Quản lý tài khoản', icon: 'accounts', to: { name: 'admin-dashboard' } }]
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
    <p class="sidebar__caption">FLIPPED LEARNING</p>
    <nav class="sidebar__nav" aria-label="Menu chính">
      <RouterLink v-for="item in navigation" :key="item.label" :to="item.to" class="sidebar__link">
        <span :class="['sidebar__icon', `sidebar__icon--${item.icon}`]" aria-hidden="true" />{{ item.label }}
      </RouterLink>
    </nav>
    <div class="sidebar__note">
      <strong>FLTS Platform</strong>
      <span>Nền tảng tạo lập học liệu thông minh cho Lớp học đảo ngược.</span>
    </div>
  </aside>
</template>
