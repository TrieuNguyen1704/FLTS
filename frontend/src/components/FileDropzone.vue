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
  >
    <input
      ref="input"
      class="sr-only"
      type="file"
      accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
      @change="fromInput"
    />
    <div class="dropzone__icon">↑</div>
    <h3>Tải lên tài liệu giảng dạy</h3>
    <p>
      Kéo thả tệp tin vào đây, hoặc
      <button type="button" class="link-button" @click="input.click()">chọn tệp từ thiết bị</button>.
    </p>
    <small>Định dạng hỗ trợ: PDF, DOC, DOCX · Dung lượng tối đa: 10 MB</small>
    <p v-if="error" class="field__error">{{ error }}</p>
  </div>
</template>
