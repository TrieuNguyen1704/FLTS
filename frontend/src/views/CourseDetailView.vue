<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import FileDropzone from '../components/FileDropzone.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'
import { toast } from '../stores/toast'
import { formatDate, formatFileSize } from '../utils/formatters'

const route = useRoute()
const router = useRouter()
const course = ref(null)
const documents = ref([])
const loading = ref(true)
const error = ref('')
const uploading = ref(false)
const documentToDelete = ref(null)
const deleting = ref(false)
const showEdit = ref(false)
const savingCourse = ref(false)
const searchQuery = ref('')
const editForm = reactive({ name: '', code: '', description: '' })
const editErrors = reactive({ name: '', code: '' })

async function loadCourse() {
  loading.value = true; error.value = ''
  try {
    const courseId = route.params.id
    const [courseResult, documentResult] = await Promise.all([courseService.get(courseId), documentService.list(courseId, searchQuery.value.trim())])
    course.value = courseResult.course; documents.value = documentResult.documents
  } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}
function openEdit() {
  Object.assign(editForm, { name: course.value.name, code: course.value.code, description: course.value.description || '' })
  editErrors.name = ''; editErrors.code = ''; showEdit.value = true
}
async function saveCourse() {
  editErrors.name = editForm.name.trim() ? '' : 'Course name is required.'
  editErrors.code = editForm.code.trim() ? '' : 'Course code is required.'
  if (editErrors.name || editErrors.code) return
  savingCourse.value = true
  try {
    course.value = (await courseService.update(course.value.id, { name: editForm.name.trim(), code: editForm.code.trim(), description: editForm.description.trim() || null })).course
    showEdit.value = false
    toast.show('Course details updated.')
  } catch (requestError) {
    const errors = requestError.errors || {}
    editErrors.name = errors.name?.[0] || ''
    editErrors.code = errors.code?.[0] || requestError.message
  } finally { savingCourse.value = false }
}
async function upload(file) {
  // The list is refreshed from the API after upload so the UI never invents a processing result locally.
  uploading.value = true
  try { await documentService.upload(course.value.id, file); await loadCourse(); toast.show('Document uploaded and marked Pending processing.') } catch (requestError) { toast.show(requestError.message, 'error') } finally { uploading.value = false }
}
async function removeDocument() {
  if (!documentToDelete.value) return
  deleting.value = true
  try { await documentService.remove(course.value.id, documentToDelete.value.id); documents.value = documents.value.filter((document) => document.id !== documentToDelete.value.id); toast.show('Document deleted.'); documentToDelete.value = null } catch (requestError) { toast.show(requestError.message, 'error') } finally { deleting.value = false }
}
async function downloadDocument(document) {
  try { await documentService.download(course.value.id, document); toast.show('Download started.') } catch (requestError) { toast.show(requestError.message, 'error') }
}
onMounted(loadCourse)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'course-management' })">← Back to courses</button>
  <AppState v-if="loading" type="loading" title="Loading course workspace" message="Retrieving course details and document metadata." />
  <AppState v-else-if="error" type="error" title="Unable to open this course" :message="error" action-label="Back to courses" @action="router.push({ name: 'course-management' })" />
  <template v-else>
    <section class="course-hero"><div><p class="eyebrow">{{ course.code }}</p><h1>{{ course.name }}</h1><p>{{ course.description || 'No description has been provided for this course.' }}</p></div><div class="course-hero__meta"><span>Lecturer</span><strong>{{ course.lecturer?.name || 'You' }}</strong><BaseButton variant="secondary" @click="openEdit">Edit course</BaseButton></div></section>
    <section class="notice-banner"><strong>What happens next?</strong><span>Uploads are persisted with metadata only. <b>Pending processing</b> means no extraction or RAG processing has occurred.</span></section>
    <section class="content-section"><header class="section-header"><div><h2>Teaching documents</h2><p>Upload a PDF, DOC, or DOCX file up to 10 MB.</p></div><span class="count-chip">{{ documents.length }} file{{ documents.length === 1 ? '' : 's' }}</span></header>
      <FileDropzone v-if="!uploading" @selected="upload" />
      <AppState v-else type="loading" title="Uploading document" message="The file is being validated and stored by the backend." />
      <form class="toolbar" @submit.prevent="loadCourse"><input v-model="searchQuery" placeholder="Search uploaded document names" aria-label="Search documents" /><BaseButton type="submit" variant="secondary">Search</BaseButton></form>
      <div v-if="documents.length" class="document-table-wrap"><table class="document-table"><thead><tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded</th><th>Status</th><th aria-label="Actions" /></tr></thead><tbody><tr v-for="document in documents" :key="document.id"><td><strong>{{ document.original_name }}</strong><small>{{ document.mime_type }}</small></td><td>{{ document.extension.toUpperCase() }}</td><td>{{ formatFileSize(document.size_bytes) }}</td><td>{{ formatDate(document.created_at) }}</td><td><StatusBadge :status="document.processing_status" /></td><td class="table-actions"><BaseButton variant="secondary" @click="downloadDocument(document)">Download</BaseButton><BaseButton variant="danger-ghost" @click="documentToDelete = document">Delete</BaseButton></td></tr></tbody></table></div>
      <AppState v-else title="No documents uploaded" message="Upload an approved teaching document to store its metadata for the next sprint." />
    </section>
  </template>
  <AppModal v-model="documentToDelete" title="Delete document" confirm-label="Delete document" :danger="true" :loading="deleting" @confirm="removeDocument"><p>Delete <strong>{{ documentToDelete?.original_name }}</strong>? This removes the stored demo file and its metadata.</p></AppModal>
  <AppModal v-model="showEdit" title="Edit course" confirm-label="Save changes" :loading="savingCourse" @confirm="saveCourse"><BaseInput v-model="editForm.name" label="Course name" :error="editErrors.name" required /><BaseInput v-model="editForm.code" label="Course code" :error="editErrors.code" required /><label class="field"><span class="field__label">Description</span><textarea v-model="editForm.description" maxlength="2000" /></label></AppModal>
</template>
