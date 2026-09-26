<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import { authService } from '../services/authService'

const router = useRouter()
const form = reactive({ name: '', email: '', role: 'lecturer', password: '', password_confirmation: '' })
const errors = reactive({ name: '', email: '', password: '', password_confirmation: '', role: '' })
const message = ref('')
const loading = ref(false)

function validate() {
  errors.name = form.name.trim() ? '' : 'Vui lòng nhập họ và tên.'
  errors.email = form.email.includes('@') ? '' : 'Vui lòng nhập địa chỉ email hợp lệ.'
  errors.password = form.password.length >= 8 ? '' : 'Mật khẩu phải chứa ít nhất 8 ký tự.'
  errors.password_confirmation = form.password === form.password_confirmation ? '' : 'Mật khẩu xác nhận không khớp.'
  return !Object.values(errors).some(Boolean)
}

async function submit() {
  message.value = ''
  if (!validate()) return
  loading.value = true
  try {
    await authService.register({ ...form, name: form.name.trim(), email: form.email.trim() })
    message.value = 'Tài khoản của bạn đã được tạo thành công. Đang chuyển hướng đến trang đăng nhập…'
    window.setTimeout(() => router.push({ name: 'login', query: { email: form.email.trim() } }), 700)
  } catch (requestError) {
    const apiErrors = requestError.errors || {}
    for (const key of Object.keys(errors)) errors[key] = apiErrors[key]?.[0] || ''
    if (!Object.values(errors).some(Boolean)) errors.email = requestError.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <main class="auth-card">
      <RouterLink class="auth-card__brand" :to="{ name: 'login' }">FLTS</RouterLink>
      <p class="eyebrow">TẠO TÀI KHOẢN MỚI</p>
      <h1>Đăng ký tài khoản</h1>
      <p class="muted">Hệ thống mở đăng ký cho Giảng viên và Sinh viên. Vai trò Quản trị viên được phân quyền nội bộ.</p>
      <form @submit.prevent="submit">
        <BaseInput v-model="form.name" label="Họ và tên" placeholder="Nguyễn Văn A" :error="errors.name" required />
        <BaseInput v-model="form.email" label="Địa chỉ Email" type="email" placeholder="ten@example.com" :error="errors.email" required />
        <label class="field">
          <span class="field__label">Vai trò tài khoản <em>*</em></span>
          <select v-model="form.role">
            <option value="lecturer">Giảng viên (Lecturer)</option>
            <option value="student">Sinh viên (Student)</option>
          </select>
        </label>
        <BaseInput v-model="form.password" label="Mật khẩu" type="password" placeholder="Tối thiểu 8 ký tự" :error="errors.password" required />
        <BaseInput v-model="form.password_confirmation" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu" :error="errors.password_confirmation" required />
        <p v-if="message" class="form-success">{{ message }}</p>
        <BaseButton type="submit" :loading="loading" class="button--full">Tạo tài khoản</BaseButton>
      </form>
      <p class="auth-card__footer">
        Đã có tài khoản? <RouterLink :to="{ name: 'login' }">Đăng nhập ngay</RouterLink>
      </p>
    </main>
  </div>
</template>
