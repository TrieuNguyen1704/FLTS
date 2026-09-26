<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import { authService } from '../services/authService'

const route = useRoute()
const router = useRouter()
const isReset = ref(Boolean(route.query.token && route.query.email))
const requestForm = reactive({ email: String(route.query.email || '') })
const resetForm = reactive({
  email: String(route.query.email || ''),
  token: String(route.query.token || ''),
  password: '',
  password_confirmation: ''
})
const message = ref('')
const error = ref('')
const loading = ref(false)

async function requestLink() {
  error.value = ''
  message.value = ''
  if (!requestForm.email.includes('@')) {
    error.value = 'Vui lòng nhập một địa chỉ email hợp lệ.'
    return
  }
  loading.value = true
  try {
    await authService.requestPasswordReset(requestForm.email.trim())
    message.value = 'Nếu email tồn tại trong hệ thống, một liên kết đặt lại mật khẩu đã được gửi đến hộp thư của bạn.'
  } catch (requestError) {
    error.value = requestError.message || 'Không thể gửi yêu cầu đặt lại mật khẩu. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

async function reset() {
  error.value = ''
  message.value = ''
  if (resetForm.password.length < 8 || resetForm.password !== resetForm.password_confirmation) {
    error.value = 'Mật khẩu phải từ 8 ký tự trở lên và mật khẩu xác nhận phải trùng khớp.'
    return
  }
  loading.value = true
  try {
    await authService.resetPassword(resetForm)
    message.value = 'Mật khẩu của bạn đã được cập nhật thành công. Đang chuyển hướng về trang đăng nhập…'
    window.setTimeout(() => router.push({ name: 'login' }), 900)
  } catch (requestError) {
    error.value = requestError.message || 'Không thể đặt lại mật khẩu. Liên kết có thể đã hết hạn.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <main class="auth-card">
      <RouterLink class="auth-card__brand" :to="{ name: 'login' }">FLTS</RouterLink>
      <template v-if="!isReset">
        <p class="eyebrow">KHÔI PHỤC MẬT KHẨU</p>
        <h1>Đặt lại mật khẩu</h1>
        <p class="muted">Nhập email tài khoản của bạn để nhận liên kết khôi phục mật khẩu qua hộp thư điện tử.</p>
        <form @submit.prevent="requestLink">
          <BaseInput v-model="requestForm.email" label="Địa chỉ Email" type="email" placeholder="ten@example.com" required />
          <p v-if="error" class="form-alert">{{ error }}</p>
          <p v-if="message" class="form-success">{{ message }}</p>
          <BaseButton type="submit" :loading="loading" class="button--full">Gửi liên kết đặt lại</BaseButton>
        </form>
      </template>
      <template v-else>
        <p class="eyebrow">CHỌN MẬT KHẨU MỚI</p>
        <h1>Thiết lập mật khẩu</h1>
        <p class="muted">Liên kết này có hiệu lực trong vòng 60 phút và chỉ có thể sử dụng một lần duy nhất.</p>
        <form @submit.prevent="reset">
          <BaseInput v-model="resetForm.password" label="Mật khẩu mới" type="password" placeholder="Tối thiểu 8 ký tự" required />
          <BaseInput v-model="resetForm.password_confirmation" label="Xác nhận mật khẩu mới" type="password" placeholder="Nhập lại mật khẩu mới" required />
          <p v-if="error" class="form-alert">{{ error }}</p>
          <p v-if="message" class="form-success">{{ message }}</p>
          <BaseButton type="submit" :loading="loading" class="button--full">Xác nhận đổi mật khẩu</BaseButton>
        </form>
      </template>
      <p class="auth-card__footer">
        <RouterLink :to="{ name: 'login' }">← Quay lại đăng nhập</RouterLink>
      </p>
    </main>
  </div>
</template>
