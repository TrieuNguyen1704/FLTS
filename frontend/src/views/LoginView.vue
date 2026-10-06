<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import { authStore } from '../stores/auth'
import { toast } from '../stores/toast'

const router = useRouter()
const route = useRoute()
const form = reactive({ email: String(route.query.email || ''), password: '' })
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  if (!form.email || !form.password) {
    error.value = 'Vui lòng nhập đầy đủ email và mật khẩu để tiếp tục.'
    return
  }
  loading.value = true
  try {
    const user = await authStore.login(form)
    toast.show(`Chào mừng trở lại, ${user.name}.`)
    const fallback = user.role === 'student'
      ? { name: 'student-dashboard' }
      : user.role === 'admin'
        ? { name: 'admin-dashboard' }
        : { name: 'lecturer-dashboard' }
    router.push(route.query.redirect || fallback)
  } catch (requestError) {
    error.value = requestError.message || 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <main class="login-card">
      <div class="brand"><span class="brand__mark">F</span><span>FLTS</span></div>
      <h1>Đăng nhập</h1>
      <p class="muted">Sử dụng tài khoản FLTS của bạn.</p>
      <form @submit.prevent="submit">
        <BaseInput v-model="form.email" label="Địa chỉ Email" type="email" placeholder="ten@example.com" required />
        <BaseInput v-model="form.password" label="Mật khẩu" type="password" placeholder="••••••••" required />
        <p v-if="error" class="form-alert" role="alert">{{ error }}</p>
        <BaseButton type="submit" :loading="loading" class="button--full">Đăng nhập</BaseButton>
      </form>
      <p class="login-card__actions">
        <RouterLink :to="{ name: 'register' }">Đăng ký tài khoản</RouterLink>
        <RouterLink :to="{ name: 'password-recovery' }">Quên mật khẩu?</RouterLink>
      </p>
    </main>
  </div>
</template>
