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
const resetForm = reactive({ email: String(route.query.email || ''), token: String(route.query.token || ''), password: '', password_confirmation: '' })
const message = ref('')
const error = ref('')
const loading = ref(false)

async function requestLink() {
  error.value = ''; message.value = ''
  if (!requestForm.email.includes('@')) { error.value = 'Enter a valid email address.'; return }
  loading.value = true
  try { message.value = (await authService.requestPasswordReset(requestForm.email.trim())).message + ' For local demo, open Mailpit at http://localhost:8025.' } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}
async function reset() {
  error.value = ''; message.value = ''
  if (resetForm.password.length < 8 || resetForm.password !== resetForm.password_confirmation) { error.value = 'Use a password of at least 8 characters and enter the same confirmation.'; return }
  loading.value = true
  try { message.value = (await authService.resetPassword(resetForm)).message; window.setTimeout(() => router.push({ name: 'login' }), 900) } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}
</script>

<template>
  <div class="auth-page"><main class="auth-card"><RouterLink class="auth-card__brand" :to="{ name: 'login' }">FLTS</RouterLink>
    <template v-if="!isReset"><p class="eyebrow">PASSWORD RECOVERY</p><h1>Reset your password</h1><p class="muted">Enter your account email. The local demo sends the one-time link to Mailpit.</p>
      <form @submit.prevent="requestLink"><BaseInput v-model="requestForm.email" label="Email address" type="email" required /><p v-if="error" class="form-alert">{{ error }}</p><p v-if="message" class="form-success">{{ message }}</p><BaseButton type="submit" :loading="loading" class="button--full">Send reset link</BaseButton></form>
    </template>
    <template v-else><p class="eyebrow">CHOOSE A NEW PASSWORD</p><h1>Set your password</h1><p class="muted">This link is valid for 60 minutes and can only be used once.</p>
      <form @submit.prevent="reset"><BaseInput v-model="resetForm.password" label="New password" type="password" required /><BaseInput v-model="resetForm.password_confirmation" label="Confirm new password" type="password" required /><p v-if="error" class="form-alert">{{ error }}</p><p v-if="message" class="form-success">{{ message }}</p><BaseButton type="submit" :loading="loading" class="button--full">Reset password</BaseButton></form>
    </template>
    <p class="auth-card__footer"><RouterLink :to="{ name: 'login' }">Back to sign in</RouterLink></p>
  </main></div>
</template>
