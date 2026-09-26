<script setup>
import { useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import { authStore } from '../stores/auth'

const router = useRouter()
async function signOut() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <AppState
    type="empty"
    title="Không có quyền truy cập"
    message="Tài khoản của bạn hiện không có quyền truy cập vào không gian làm việc này."
    action-label="Đăng xuất"
    @action="signOut"
  />
</template>
