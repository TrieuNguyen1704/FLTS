<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import FileDropzone from '../components/FileDropzone.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'
import { ragService } from '../services/ragService'
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
const processingIds = ref({})
const retrievalQuery = ref('')
const retrievalLoading = ref(false)
const retrievalError = ref('')
const retrievalResult = ref(null)
const evidenceLoading = ref(false)
const evidenceResult = ref(null)

async function loadCourse() {
  loading.value = true
  error.value = ''
  try {
    const courseId = route.params.id
    const [courseResult, documentResult] = await Promise.all([
      courseService.get(courseId),
      documentService.list(courseId, searchQuery.value.trim())
    ])
    course.value = courseResult.course
    documents.value = documentResult.documents
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải thông tin khóa học.'
  } finally {
    loading.value = false
  }
}

function openEdit() {
  Object.assign(editForm, {
    name: course.value.name,
    code: course.value.code,
    description: course.value.description || ''
  })
  editErrors.name = ''
  editErrors.code = ''
  showEdit.value = true
}

async function saveCourse() {
  editErrors.name = editForm.name.trim() ? '' : 'Vui lòng nhập tên khóa học.'
  editErrors.code = editForm.code.trim() ? '' : 'Vui lòng nhập mã khóa học.'
  if (editErrors.name || editErrors.code) return
  savingCourse.value = true
  try {
    course.value = (await courseService.update(course.value.id, {
      name: editForm.name.trim(),
      code: editForm.code.trim(),
      description: editForm.description.trim() || null
    })).course
    showEdit.value = false
    toast.show('Thông tin khóa học đã được cập nhật thành công.')
  } catch (requestError) {
    const errors = requestError.errors || {}
    editErrors.name = errors.name?.[0] || ''
    editErrors.code = errors.code?.[0] || requestError.message
  } finally {
    savingCourse.value = false
  }
}

async function upload(file) {
  uploading.value = true
  try {
    await documentService.upload(course.value.id, file)
    await loadCourse()
    toast.show('Tải lên tài liệu thành công. Trạng thái: Chờ xử lý.')
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tải lên tài liệu.', 'error')
  } finally {
    uploading.value = false
  }
}

async function removeDocument() {
  if (!documentToDelete.value) return
  deleting.value = true
  try {
    await documentService.remove(course.value.id, documentToDelete.value.id)
    documents.value = documents.value.filter((document) => document.id !== documentToDelete.value.id)
    toast.show('Đã xóa tài liệu.')
    documentToDelete.value = null
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể xóa tài liệu.', 'error')
  } finally {
    deleting.value = false
  }
}

async function downloadDocument(document) {
  try {
    await documentService.download(course.value.id, document)
    toast.show('Đang bắt đầu tải xuống...')
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tải xuống tài liệu.', 'error')
  }
}

async function processDocument(document) {
  processingIds.value = { ...processingIds.value, [document.id]: true }
  try {
    const request = document.processing_status === 'failed'
      ? ragService.retryProcessing(course.value.id, document.id)
      : ragService.startProcessing(course.value.id, document.id)
    await request
    toast.show('Tài liệu đã được đưa vào hàng đợi xử lý RAG.')
    await waitForProcessing(document.id)
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể bắt đầu xử lý tài liệu.', 'error')
  } finally {
    const next = { ...processingIds.value }
    delete next[document.id]
    processingIds.value = next
    await loadCourse()
  }
}

async function waitForProcessing(documentId) {
  // Poll only while this user initiated a run; the server remains the source of truth for its state.
  for (let attempts = 0; attempts < 45; attempts += 1) {
    await new Promise((resolve) => window.setTimeout(resolve, 2000))
    const result = await ragService.processingStatus(course.value.id, documentId)
    const status = result.document.processing_status
    if (status === 'processed') {
      toast.show(`Đã xử lý tài liệu: ${result.run?.chunk_count || 0} đoạn văn bản có thể truy xuất.`)
      return
    }
    if (status === 'failed') {
      throw new Error(result.run?.error_detail?.message || 'Xử lý tài liệu thất bại.')
    }
  }
  toast.show('Tài liệu vẫn đang xử lý trong nền. Bạn có thể tải lại trang để xem trạng thái mới nhất.')
}

async function runRetrieval() {
  retrievalError.value = ''
  retrievalResult.value = null
  evidenceResult.value = null
  if (retrievalQuery.value.trim().length < 3) {
    retrievalError.value = 'Vui lòng nhập câu hỏi có ít nhất 3 ký tự.'
    return
  }
  retrievalLoading.value = true
  try {
    retrievalResult.value = await ragService.search(course.value.id, { query: retrievalQuery.value.trim(), top_k: 5 })
  } catch (requestError) {
    retrievalError.value = requestError.message || 'Không thể truy xuất tài liệu.'
  } finally {
    retrievalLoading.value = false
  }
}

async function createEvidence() {
  if (!retrievalQuery.value.trim()) return
  evidenceLoading.value = true
  retrievalError.value = ''
  evidenceResult.value = null
  try {
    evidenceResult.value = await ragService.generateEvidence(course.value.id, { prompt: retrievalQuery.value.trim(), top_k: 5 })
  } catch (requestError) {
    retrievalError.value = requestError.message || 'Không thể tạo phản hồi có dẫn chứng.'
  } finally {
    evidenceLoading.value = false
  }
}

onMounted(loadCourse)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'course-management' })">← Quay lại danh sách khóa học</button>
  <AppState v-if="loading" type="loading" title="Đang tải dữ liệu khóa học" message="Đang lấy thông tin khóa học và danh sách tài liệu." />
  <AppState v-else-if="error" type="error" title="Không thể mở khóa học này" :message="error" action-label="Quay lại danh sách khóa học" @action="router.push({ name: 'course-management' })" />
  <template v-else>
    <section class="course-hero">
      <div>
        <p class="eyebrow">{{ course.code }}</p>
        <h1>{{ course.name }}</h1>
        <p>{{ course.description || 'Chưa có mô tả cho khóa học này.' }}</p>
      </div>
      <div class="course-hero__meta">
        <span>Giảng viên phụ trách</span>
        <strong>{{ course.lecturer?.name || 'Bạn' }}</strong>
        <BaseButton variant="secondary" @click="openEdit">Chỉnh sửa khóa học</BaseButton>
      </div>
    </section>
    <section class="notice-banner">
      <strong>Quy trình tài liệu</strong>
      <span>Tài liệu mới tải lên ở trạng thái <b>Chờ xử lý</b>. Chọn <b>Xử lý RAG</b> để đưa tài liệu vào hàng đợi trích xuất, chia đoạn và lập chỉ mục; chỉ tài liệu xử lý thành công mới có thể truy xuất.</span>
    </section>
    <section class="content-section">
      <header class="section-header">
        <div>
          <h2>Tài liệu giảng dạy</h2>
          <p>Hỗ trợ tải lên tệp tin PDF, DOC hoặc DOCX dung lượng tối đa 10 MB.</p>
        </div>
        <span class="count-chip">{{ documents.length }} tài liệu</span>
      </header>
      <FileDropzone v-if="!uploading" @selected="upload" />
      <AppState v-else type="loading" title="Đang tải lên tài liệu" message="Tệp tin đang được xác thực và lưu trữ vào hệ thống." />
      <form class="toolbar" @submit.prevent="loadCourse">
        <input v-model="searchQuery" placeholder="Tìm kiếm tài liệu theo tên..." aria-label="Tìm kiếm tài liệu" />
        <BaseButton type="submit" variant="secondary">Tìm kiếm</BaseButton>
      </form>
      <div v-if="documents.length" class="document-table-wrap">
        <table class="document-table">
          <thead>
            <tr>
              <th>Tài liệu</th>
              <th>Định dạng</th>
              <th>Dung lượng</th>
              <th>Thời gian tải</th>
              <th>Trạng thái</th>
              <th aria-label="Thao tác" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="document in documents" :key="document.id">
              <td>
                <strong>{{ document.original_name }}</strong>
                <small>{{ document.mime_type }}</small>
              </td>
              <td>{{ document.extension.toUpperCase() }}</td>
              <td>{{ formatFileSize(document.size_bytes) }}</td>
              <td>{{ formatDate(document.created_at) }}</td>
              <td><StatusBadge :status="document.processing_status" /></td>
              <td class="table-actions">
                <BaseButton variant="secondary" @click="downloadDocument(document)">Tải về</BaseButton>
                <BaseButton
                  v-if="document.processing_status !== 'processing'"
                  variant="secondary"
                  :loading="Boolean(processingIds[document.id])"
                  @click="processDocument(document)"
                >{{ document.processing_status === 'failed' ? 'Thử lại RAG' : 'Xử lý RAG' }}</BaseButton>
                <BaseButton variant="danger-ghost" @click="documentToDelete = document">Xóa</BaseButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppState v-else title="Chưa có tài liệu nào" message="Tải lên tài liệu giảng dạy đầu tiên để phục vụ cho việc tạo học liệu." />
    </section>
    <section class="content-section rag-workbench">
      <header class="section-header">
        <div>
          <h2>Kiểm tra truy xuất RAG</h2>
          <p>Chỉ tìm trong các tài liệu đã xử lý thành công. Kết quả hiển thị đoạn nguồn thực tế từ ChromaDB.</p>
        </div>
      </header>
      <form class="toolbar" @submit.prevent="runRetrieval">
        <input v-model="retrievalQuery" maxlength="2000" placeholder="Nhập câu hỏi về tài liệu đã xử lý..." aria-label="Câu hỏi truy xuất RAG" />
        <BaseButton type="submit" :loading="retrievalLoading">Tìm đoạn nguồn</BaseButton>
        <BaseButton v-if="retrievalResult?.matches?.length" type="button" variant="secondary" :loading="evidenceLoading" @click="createEvidence">Tạo phản hồi có dẫn chứng</BaseButton>
      </form>
      <p v-if="retrievalError" class="form-error">{{ retrievalError }}</p>
      <div v-if="retrievalResult" class="rag-results">
        <p v-if="!retrievalResult.matches.length" class="muted">Không có đoạn tài liệu phù hợp. Hãy xử lý ít nhất một tài liệu trước.</p>
        <article v-for="match in retrievalResult.matches" :key="match.vector_id" class="rag-result-card">
          <header><strong>{{ match.document_name }}</strong><span>Độ phù hợp: {{ match.score }}</span></header>
          <small>{{ match.source_locator || 'Vị trí nguồn chưa xác định' }}</small>
          <p>{{ match.content }}</p>
        </article>
      </div>
      <article v-if="evidenceResult" class="evidence-card">
        <h3>Phản hồi có dẫn chứng</h3>
        <p>{{ evidenceResult.evidence.answer }}</p>
        <p><strong>Giới hạn:</strong> {{ evidenceResult.evidence.limitations }}</p>
        <ul><li v-for="citation in evidenceResult.evidence.citations" :key="citation.vector_id">{{ citation.vector_id }}{{ citation.source_locator ? ` — ${citation.source_locator}` : '' }}</li></ul>
      </article>
    </section>
  </template>
  <AppModal
    v-model="documentToDelete"
    title="Xác nhận xóa tài liệu"
    confirm-label="Xóa tài liệu"
    :danger="true"
    :loading="deleting"
    @confirm="removeDocument"
  >
    <p>Bạn có chắc chắn muốn xóa tài liệu <strong>{{ documentToDelete?.original_name }}</strong>? Thao tác này sẽ xóa tệp tin và toàn bộ dữ liệu liên quan.</p>
  </AppModal>
  <AppModal
    v-model="showEdit"
    title="Chỉnh sửa thông tin khóa học"
    confirm-label="Lưu thay đổi"
    :loading="savingCourse"
    @confirm="saveCourse"
  >
    <BaseInput v-model="editForm.name" label="Tên khóa học" :error="editErrors.name" required />
    <BaseInput v-model="editForm.code" label="Mã khóa học" :error="editErrors.code" required />
    <label class="field">
      <span class="field__label">Mô tả khóa học</span>
      <textarea v-model="editForm.description" maxlength="2000" placeholder="Nhập mô tả cho khóa học..." />
    </label>
  </AppModal>
</template>
