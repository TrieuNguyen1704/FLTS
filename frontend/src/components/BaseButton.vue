<script setup>
defineProps({
  variant: { type: String, default: 'primary' },
  loading: Boolean,
  type: { type: String, default: 'button' },
})
</script>

<template>
  <button :type="type" class="button" :class="`button--${variant}`" :disabled="loading">
    <span v-if="loading" class="button__spinner" aria-hidden="true" />
    <slot />
  </button>
</template>
