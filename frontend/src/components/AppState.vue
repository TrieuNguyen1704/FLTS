<script setup>
import BaseButton from './BaseButton.vue'
defineProps({ type: { type: String, default: 'empty' }, title: { type: String, required: true }, message: { type: String, required: true }, actionLabel: String })
defineEmits(['action'])
</script>

<template>
  <div class="app-state" :class="`app-state--${type}`">
    <h3>{{ title }}</h3><p>{{ message }}</p>
    <BaseButton v-if="actionLabel" variant="secondary" @click="$emit('action')">{{ actionLabel }}</BaseButton>
  </div>
</template>
