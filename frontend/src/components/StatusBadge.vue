<script setup>
import { computed } from 'vue'

const props = defineProps({ status: { type: String, required: true } })

const displayStatus = computed(() => {
  switch (props.status) {
    case 'uploaded_pending_processing':
      return 'Chờ xử lý'
    case 'processing':
      return 'Đang xử lý'
    case 'processed':
      return 'Đã xử lý'
    case 'failed':
      return 'Thất bại'
    default:
      return props.status
  }
})
</script>

<template>
  <span class="status-badge" :class="`status-badge--${status}`">{{ displayStatus }}</span>
</template>
