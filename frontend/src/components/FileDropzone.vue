<script setup>
import { ref } from 'vue'

const emit = defineEmits(['selected'])
const dragging = ref(false)
const input = ref(null)
const error = ref('')

function validate(file) {
  // This gives immediate feedback only; Laravel validates the same limit before any file is stored.
  const extension = file?.name?.split('.').pop()?.toLowerCase()
  if (!file || !['pdf', 'doc', 'docx'].includes(extension)) return 'Please choose a PDF, DOC, or DOCX file.'
  if (file.size > 10 * 1024 * 1024) return 'This file is larger than the 10 MB demo limit.'
  return ''
}

function select(file) {
  error.value = validate(file)
  if (!error.value) emit('selected', file)
}

function fromInput(event) { select(event.target.files?.[0]); event.target.value = '' }
function drop(event) { dragging.value = false; select(event.dataTransfer.files?.[0]) }
</script>

<template>
  <div class="dropzone" :class="{ 'dropzone--active': dragging }" @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="drop">
    <input ref="input" class="sr-only" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="fromInput" />
    <div class="dropzone__icon">↑</div><h3>Upload a teaching document</h3>
    <p>Drop one file here, or <button type="button" class="link-button" @click="input.click()">browse files</button>.</p>
    <small>Accepted: PDF, DOC, DOCX · Maximum size: 10 MB</small>
    <p v-if="error" class="field__error">{{ error }}</p>
  </div>
</template>
