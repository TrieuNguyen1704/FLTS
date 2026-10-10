<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { authStore } from '../stores/auth'
import { toast } from '../stores/toast'
import BackgroundTaskCenter from './BackgroundTaskCenter.vue'

const router = useRouter()

async function signOut() {
  await authStore.logout()
  toast.show('Bạn đã đăng xuất khỏi hệ thống.')
  router.push({ name: 'login' })
}

function roleLabel(role) {
  if (role === 'admin') return 'Quản trị viên'
  if (role === 'lecturer') return 'Giảng viên'
  if (role === 'student') return 'Sinh viên'
  return role || 'Người dùng'
}

const userInitial = computed(() => {
  const name = authStore.user.value?.name || ''
  if (!name) return 'U'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
})
</script>

<template>
  <header class="topbar">
    <div class="topbar__brand">
      <span class="topbar__logo">FLTS</span>
      <span class="topbar__divider">|</span>
      <span class="topbar__tagline">Flipped Learning Platform</span>
    </div>

    <div class="topbar__right">
      <BackgroundTaskCenter />

      <div class="topbar__account">
        <div class="topbar__avatar" :title="authStore.user.value?.name">
          {{ userInitial }}
        </div>
        <div class="topbar__identity">
          <strong>{{ authStore.user.value?.name }}</strong>
          <span class="topbar__role-tag">{{ roleLabel(authStore.user.value?.role) }}</span>
        </div>
        <button class="topbar__signout-btn" @click="signOut" title="Đăng xuất khỏi hệ thống">
          Đăng xuất
        </button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  display: flex;
  min-height: 64px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--cds-border);
  padding: 10px clamp(16px, 3vw, 40px);
  background: #ffffff;
  position: sticky;
  top: 0;
  z-index: 100;
}

.topbar__brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.topbar__logo {
  font-size: 1.25rem;
  font-weight: 850;
  letter-spacing: -0.03em;
  color: var(--cds-blue-primary);
}

.topbar__divider {
  color: var(--cds-border);
  font-weight: 300;
  font-size: 0.9rem;
}

.topbar__tagline {
  color: var(--cds-text-secondary);
  font-size: 0.8rem;
  font-weight: 500;
}

.topbar__right {
  display: flex;
  align-items: center;
  gap: 18px;
}

.topbar__account {
  display: flex;
  align-items: center;
  gap: 10px;
}

.topbar__avatar {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 50%;
  background: var(--cds-blue-tint);
  color: var(--cds-blue-primary);
  font-size: 0.82rem;
  font-weight: 750;
  border: 1.5px solid #d0e2ff;
  user-select: none;
}

.topbar__identity {
  display: grid;
  gap: 2px;
  font-size: 0.82rem;
  text-align: right;
}

.topbar__identity strong {
  color: var(--cds-text-primary);
  font-weight: 650;
}

.topbar__role-tag {
  color: var(--cds-text-secondary);
  font-size: 0.72rem;
}

.topbar__signout-btn {
  border: 1px solid var(--cds-border);
  padding: 6px 12px;
  background: #ffffff;
  border-radius: var(--cds-radius-sm);
  color: var(--cds-text-secondary);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  margin-left: 4px;
}

.topbar__signout-btn:hover {
  background: var(--cds-blue-tint);
  border-color: var(--cds-blue-primary);
  color: var(--cds-blue-primary);
}

@media (max-width: 768px) {
  .topbar__divider,
  .topbar__tagline {
    display: none;
  }
}

@media (max-width: 650px) {
  .topbar__identity {
    display: none;
  }
}
</style>
