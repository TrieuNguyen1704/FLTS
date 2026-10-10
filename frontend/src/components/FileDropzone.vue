<script setup>
import { ref } from 'vue'

const emit = defineEmits(['selected'])
const dragging = ref(false)
const input = ref(null)
const error = ref('')

function validate(file) {
  const extension = file?.name?.split('.').pop()?.toLowerCase()
  if (!file || !['pdf', 'doc', 'docx'].includes(extension)) {
    return 'Vui lòng chọn tệp tin có định dạng PDF, DOC hoặc DOCX.'
  }
  if (file.size > 10 * 1024 * 1024) {
    return 'Dung lượng tệp tin vượt quá giới hạn tối đa 10 MB.'
  }
  return ''
}

function select(file) {
  error.value = validate(file)
  if (!error.value) emit('selected', file)
}

function fromInput(event) {
  select(event.target.files?.[0])
  event.target.value = ''
}

function drop(event) {
  dragging.value = false
  select(event.dataTransfer.files?.[0])
}
</script>

<template>
  <div
    class="dropzone"
    :class="{ 'dropzone--active': dragging }"
    @dragover.prevent="dragging = true"
    @dragleave="dragging = false"
    @drop.prevent="drop"
    @click="input.click()"
  >
    <input
      ref="input"
      class="sr-only"
      type="file"
      accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
      @change="fromInput"
    />
    <div class="dropzone__icon">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="17 8 12 3 7 8" />
        <line x1="12" y1="3" x2="12" y2="15" />
      </svg>
    </div>
    <h3>Tải lên tài liệu giáo trình</h3>
    <p>
      Kéo thả tệp tin vào đây, hoặc
      <button type="button" class="link-button" @click.stop="input.click()">chọn tệp từ máy tính</button>
    </p>
    <small>Hỗ trợ tài liệu: PDF, DOC, DOCX · Tối đa: 10 MB</small>
    <p v-if="error" class="field__error">{{ error }}</p>
  </div>
</template>
