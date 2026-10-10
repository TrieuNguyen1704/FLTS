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

function fillDemo(email, pass = 'password') {
  form.email = email
  form.password = pass
  error.value = ''
}

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
    <!-- Left Hero Banner: Coursera EdTech Style -->
    <div class="login-page__intro">
      <div class="brand brand--light">
        <span class="brand__mark">F</span>
        <span>FLTS</span>
      </div>
      <p class="login-page__eyebrow">FLIPPED LEARNING TEACHING SYSTEM</p>
      <h1>Nâng cao chất lượng dạy và học với AI & RAG</h1>
      <p>
        Nền tảng hỗ trợ toàn diện mô hình Lớp học đảo ngược: Tự động trích xuất tri thức từ giáo trình PDF, biên soạn câu hỏi trắc nghiệm theo chuẩn Bloom và kiểm tra mức độ sẵn sàng của sinh viên.
      </p>
    </div>

    <!-- Right Login Card -->
    <main class="login-card">
      <div class="login-card__header">
        <h1>Đăng nhập</h1>
        <p class="muted">Chào mừng trở lại! Vui lòng nhập thông tin để truy cập hệ thống.</p>
      </div>

      <form @submit.prevent="submit">
        <BaseInput
          v-model="form.email"
          label="Địa chỉ Email"
          type="email"
          placeholder="ten@example.com"
          required
        />
        <BaseInput
          v-model="form.password"
          label="Mật khẩu"
          type="password"
          placeholder="••••••••"
          required
        />

        <p v-if="error" class="form-alert" role="alert">{{ error }}</p>

        <BaseButton type="submit" :loading="loading" class="button--full">
          Đăng nhập vào FLTS
        </BaseButton>
      </form>

      <!-- Quick Demo Access Pills for Capstone Evaluation -->
      <div class="demo-accounts">
        <span>Tài khoản Demo:</span>
        <button type="button" @click="fillDemo('lecturer@flts.test')">Giảng viên</button>
        <button type="button" @click="fillDemo('student@flts.test')">Sinh viên</button>
        <button type="button" @click="fillDemo('admin@flts.test')">Admin</button>
      </div>

      <p class="login-card__actions">
        <RouterLink :to="{ name: 'register' }">Đăng ký tài khoản mới</RouterLink>
        <RouterLink :to="{ name: 'password-recovery' }">Quên mật khẩu?</RouterLink>
      </p>
    </main>
  </div>
</template>

<style scoped>
.login-card__header h1 {
  font-size: 1.75rem;
  font-weight: 750;
  color: var(--cds-text-primary);
  margin-bottom: 6px;
}
</style>
