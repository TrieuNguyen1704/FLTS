<script setup>
import { useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
const router = useRouter()
</script>

<template>
  <main class="not-found">
    <p class="eyebrow">404</p>
    <h1>Trang này không tồn tại.</h1>
    <p>Địa chỉ bạn truy cập có thể đã bị thay đổi hoặc không còn khả dụng.</p>
    <BaseButton @click="router.push('/')">Quay về trang chủ FLTS</BaseButton>
  </main>
</template>
