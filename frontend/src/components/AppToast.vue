<script setup>
import { toast } from '../stores/toast'
</script>

<template>
  <Transition name="toast">
    <button v-if="toast.state.visible" class="toast" :class="`toast--${toast.state.tone}`" @click="toast.hide">
      <strong>{{ toast.state.tone === 'error' ? 'Cần kiểm tra' : 'Đã cập nhật' }}</strong>
      <span>{{ toast.state.message }}</span>
    </button>
  </Transition>
</template>
