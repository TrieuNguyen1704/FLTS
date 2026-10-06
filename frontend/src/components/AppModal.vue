<script setup>
import BaseButton from './BaseButton.vue'

defineProps({
  modelValue: Boolean,
  title: { type: String, required: true },
  confirmLabel: { type: String, default: 'Xác nhận' },
  danger: Boolean,
  loading: Boolean
})
defineEmits(['update:modelValue', 'confirm'])
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="modal-backdrop" @click.self="$emit('update:modelValue', false)">
      <section class="modal" role="dialog" aria-modal="true" :aria-label="title">
        <header class="modal__header">
          <h2>{{ title }}</h2>
          <button class="text-button" aria-label="Đóng" @click="$emit('update:modelValue', false)">Đóng</button>
        </header>
        <div class="modal__body"><slot /></div>
        <footer class="modal__footer">
          <BaseButton variant="secondary" @click="$emit('update:modelValue', false)">Hủy</BaseButton>
          <BaseButton :variant="danger ? 'danger' : 'primary'" :loading="loading" @click="$emit('confirm')">
            {{ confirmLabel }}
          </BaseButton>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
