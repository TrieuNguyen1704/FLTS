<script setup>
import { useRouter } from 'vue-router'
import { authStore } from '../stores/auth'
import { toast } from '../stores/toast'

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
    <strong>FLTS</strong>
    <div class="topbar__account">
      <div class="topbar__identity">
        <strong>{{ authStore.user.value?.name }}</strong>
        <span>{{ roleLabel(authStore.user.value?.role) }}</span>
      </div>
      <button class="text-button" @click="signOut">Đăng xuất</button>
    </div>
  </header>
</template>
