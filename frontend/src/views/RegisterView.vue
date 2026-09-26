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
  errors.name = form.name.trim() ? '' : 'Name is required.'
  errors.email = form.email.includes('@') ? '' : 'Enter a valid email address.'
  errors.password = form.password.length >= 8 ? '' : 'Password must contain at least 8 characters.'
  errors.password_confirmation = form.password === form.password_confirmation ? '' : 'Passwords do not match.'
  return !Object.values(errors).some(Boolean)
}

async function submit() {
  message.value = ''
  if (!validate()) return
  loading.value = true
  try {
    await authService.register({ ...form, name: form.name.trim(), email: form.email.trim() })
    message.value = 'Your account has been created. Redirecting to sign in…'
    window.setTimeout(() => router.push({ name: 'login', query: { email: form.email.trim() } }), 700)
  } catch (requestError) {
    const apiErrors = requestError.errors || {}
    for (const key of Object.keys(errors)) errors[key] = apiErrors[key]?.[0] || ''
    if (!Object.values(errors).some(Boolean)) errors.email = requestError.message
  } finally { loading.value = false }
}
</script>

<template>
  <div class="auth-page">
    <main class="auth-card"><RouterLink class="auth-card__brand" :to="{ name: 'login' }">FLTS</RouterLink>
      <p class="eyebrow">NEW ACCOUNT</p><h1>Create your account</h1><p class="muted">Lecturer and Student accounts can register here. Administrator roles are assigned through the protected Admin workspace.</p>
      <form @submit.prevent="submit">
        <BaseInput v-model="form.name" label="Full name" :error="errors.name" required />
        <BaseInput v-model="form.email" label="Email address" type="email" :error="errors.email" required />
        <label class="field"><span class="field__label">Account role <em>*</em></span><select v-model="form.role"><option value="lecturer">Lecturer</option><option value="student">Student</option></select></label>
        <BaseInput v-model="form.password" label="Password" type="password" :error="errors.password" required />
        <BaseInput v-model="form.password_confirmation" label="Confirm password" type="password" :error="errors.password_confirmation" required />
        <p v-if="message" class="form-success">{{ message }}</p>
        <BaseButton type="submit" :loading="loading" class="button--full">Create account</BaseButton>
      </form>
      <p class="auth-card__footer">Already have an account? <RouterLink :to="{ name: 'login' }">Sign in</RouterLink></p>
    </main>
  </div>
</template>
