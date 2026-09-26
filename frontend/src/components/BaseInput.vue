<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  required: Boolean,
})
defineEmits(['update:modelValue'])
</script>

<template>
  <label class="field">
    <span class="field__label">{{ label }} <em v-if="required">*</em></span>
    <input :value="modelValue" :type="type" :placeholder="placeholder" :aria-invalid="Boolean(error)" @input="$emit('update:modelValue', $event.target.value)" />
    <span v-if="error" class="field__error">{{ error }}</span>
  </label>
</template>
