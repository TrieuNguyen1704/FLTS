<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import { authStore } from '../stores/auth'
import { toast } from '../stores/toast'

const router = useRouter()
const route = useRoute()
const form = reactive({ email: String(route.query.email || 'lecturer@flts.test'), password: 'DemoPass123!' })
const error = ref('')
const loading = ref(false)

function selectAccount(email) { form.email = email; form.password = 'DemoPass123!'; error.value = '' }

async function submit() {
  // Client checks improve feedback, while the API remains the source of truth for credential validation.
  error.value = ''
  if (!form.email || !form.password) { error.value = 'Enter your email and password to continue.'; return }
  loading.value = true
  try {
    const user = await authStore.login(form)
    toast.show(`Welcome back, ${user.name}.`)
    const fallback = user.role === 'student' ? { name: 'student-dashboard' } : user.role === 'admin' ? { name: 'admin-dashboard' } : { name: 'lecturer-dashboard' }
    router.push(route.query.redirect || fallback)
  } catch (requestError) {
    error.value = requestError.message
  } finally { loading.value = false }
}
</script>

<template>
  <div class="login-page">
    <section class="login-page__intro">
      <div class="brand brand--light"><span class="brand__mark">F</span><span>FLTS</span></div>
      <p class="login-page__eyebrow">FLIPPED LEARNING TOOL SUPPORT</p>
      <h1>A focused workspace for teaching materials.</h1>
      <p>Manage course documents with clear access control. This Sprint 1 demo stores uploads only; document processing and RAG are not available yet.</p>
    </section>
    <main class="login-card">
      <p class="eyebrow">WELCOME BACK</p><h2>Sign in to FLTS</h2><p class="muted">Use a seeded demo account to explore the implemented Sprint 1 flows.</p>
      <form @submit.prevent="submit">
        <BaseInput v-model="form.email" label="Email address" type="email" placeholder="you@example.com" required />
        <BaseInput v-model="form.password" label="Password" type="password" required />
        <p v-if="error" class="form-alert" role="alert">{{ error }}</p>
        <BaseButton type="submit" :loading="loading" class="button--full">Sign in</BaseButton>
      </form>
      <div class="demo-accounts"><span>Demo accounts</span><button type="button" @click="selectAccount('lecturer@flts.test')">Lecturer</button><button type="button" @click="selectAccount('student@flts.test')">Student</button><button type="button" @click="selectAccount('admin@flts.test')">Administrator</button></div>
      <p class="login-card__note">Password for all demo accounts: <code>DemoPass123!</code></p>
      <p class="login-card__actions"><RouterLink :to="{ name: 'register' }">Create account</RouterLink><RouterLink :to="{ name: 'password-recovery' }">Forgot password?</RouterLink></p>
    </main>
  </div>
</template>
