<script setup>
import BaseButton from './BaseButton.vue'

defineProps({
  type: { type: String, default: 'empty' },
  title: { type: String, required: true },
  message: { type: String, required: true },
  actionLabel: String,
})
defineEmits(['action'])
</script>

<template>
  <div class="app-state" :class="`app-state--${type}`">
    <div class="app-state__icon">
      <!-- Loading spinner -->
      <svg
        v-if="type === 'loading'"
        viewBox="0 0 24 24"
        width="22"
        height="22"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        class="spinner"
      >
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4" />
      </svg>

      <!-- Error alert -->
      <svg
        v-else-if="type === 'error'"
        viewBox="0 0 24 24"
        width="22"
        height="22"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
      >
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>

      <!-- Empty inbox/document -->
      <svg
        v-else
        viewBox="0 0 24 24"
        width="22"
        height="22"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <path d="M22 12h-6l-2 3h-4l-2-3H2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-7z" />
        <path d="M5.45 5.11L2 12v0h20v0l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
      </svg>
    </div>

    <h3>{{ title }}</h3>
    <p>{{ message }}</p>

    <BaseButton v-if="actionLabel" variant="secondary" @click="$emit('action')">
      {{ actionLabel }}
    </BaseButton>
  </div>
</template>

<style scoped>
.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
