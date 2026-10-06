<script setup>
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
</script>

<template>
  <header class="topbar">
    <div class="topbar__brand">
      <span class="topbar__logo">FLTS</span>
    </div>

    <div class="topbar__right">
      <BackgroundTaskCenter />

      <div class="topbar__account">
        <div class="topbar__identity">
          <strong>{{ authStore.user.value?.name }}</strong>
          <span>{{ roleLabel(authStore.user.value?.role) }}</span>
        </div>
        <button class="text-button" @click="signOut">Đăng xuất</button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  display: flex;
  min-height: 62px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid #e6eaf3;
  padding: 10px clamp(20px, 4vw, 54px);
  background: #fff;
}

.topbar__brand {
  display: flex;
  align-items: center;
}

.topbar__logo {
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #17275a;
}

.topbar__right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.topbar__account {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar__identity {
  display: grid;
  gap: 1px;
  font-size: 0.8rem;
  text-align: right;
}

.topbar__identity strong {
  color: #17275a;
}

.topbar__identity span {
  color: #73809c;
  font-size: 0.72rem;
}

.text-button {
  border: 0;
  padding: 5px 8px;
  background: #f0f3fa;
  border-radius: 4px;
  color: #38476b;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.text-button:hover {
  background: #e2e8f5;
  color: #17275a;
}

@media (max-width: 650px) {
  .topbar__identity {
    display: none;
  }
}
</style>
