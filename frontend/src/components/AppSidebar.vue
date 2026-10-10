<script setup>
import { computed } from 'vue'
import { authStore } from '../stores/auth'

const navigation = computed(() => {
  if (authStore.role.value === 'student') {
    return [
      {
        label: 'Khóa học của tôi',
        to: { name: 'student-dashboard' },
        icon: 'book',
      },
    ]
  }
  if (authStore.role.value === 'lecturer') {
    return [
      {
        label: 'Tổng quan',
        to: { name: 'lecturer-dashboard' },
        icon: 'dashboard',
      },
      {
        label: 'Khóa học',
        to: { name: 'course-management' },
        icon: 'book',
      },
    ]
  }
  if (authStore.role.value === 'admin') {
    return [
      {
        label: 'Tài khoản',
        to: { name: 'admin-dashboard' },
        icon: 'users',
      },
    ]
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
      <span class="brand__mark">F</span>
      <span>FLTS</span>
    </RouterLink>

    <p class="sidebar__caption">MENU ĐIỀU HƯỚNG</p>

    <nav class="sidebar__nav" aria-label="Menu chính">
      <RouterLink
        v-for="item in navigation"
        :key="item.label"
        :to="item.to"
        class="sidebar__link"
      >
        <!-- Dashboard Icon -->
        <svg
          v-if="item.icon === 'dashboard'"
          class="sidebar__nav-icon"
          viewBox="0 0 24 24"
          width="18"
          height="18"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <rect x="3" y="3" width="7" height="9" rx="1" />
          <rect x="14" y="3" width="7" height="5" rx="1" />
          <rect x="14" y="12" width="7" height="9" rx="1" />
          <rect x="3" y="16" width="7" height="5" rx="1" />
        </svg>

        <!-- Book / Course Icon -->
        <svg
          v-else-if="item.icon === 'book'"
          class="sidebar__nav-icon"
          viewBox="0 0 24 24"
          width="18"
          height="18"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
        </svg>

        <!-- Users Icon -->
        <svg
          v-else-if="item.icon === 'users'"
          class="sidebar__nav-icon"
          viewBox="0 0 24 24"
          width="18"
          height="18"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>

        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>

    <div class="sidebar__note">
      <strong>FLTS AI EdTech</strong>
      <span>Tự động hóa học liệu và kiểm tra theo mô hình Flipped Classroom.</span>
    </div>
  </aside>
</template>

<style scoped>
.sidebar__nav-icon {
  flex-shrink: 0;
  opacity: 0.85;
}

.sidebar__link.router-link-active .sidebar__nav-icon {
  opacity: 1;
  stroke: var(--cds-blue-primary);
}
</style>
