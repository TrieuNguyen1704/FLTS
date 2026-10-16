<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'
import { learningObjectService } from '../services/learningObjectService'
import { backgroundTasks } from '../stores/backgroundTasks'
import { toast } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const course = ref(null)
const documents = ref([])
const objects = ref([])
const activeType = ref(route.query.type === 'flashcard' ? 'flashcard' : 'quiz')
const loading = ref(true)
const submitting = ref(false)
const retryingId = ref(null)
const objectToDelete = ref(null)
const deleting = ref(false)
const error = ref('')
const activeObjectId = ref(null)
let pollTimer = null

const quizForm = reactive({ title: '', topic: '', difficulty: 'medium', question_count: 5, top_k: 8, passing_score: 60, time_limit_minutes: null, document_ids: [] })
const flashcardForm = reactive({ title: '', topic: '', difficulty: 'medium', card_count: 8, top_k: 10, max_pages: 5, document_ids: [] })
const processedDocuments = computed(() => documents.value.filter((document) => document.processing_status === 'processed'))
const visibleObjects = computed(() => objects.value.filter((object) => object.type === activeType.value))
const hasPendingGeneration = computed(() => objects.value.some((object) => ['queued', 'generating'].includes(object.generation?.status)))

function typeLabel(type) { return type === 'flashcard' ? 'Flashcard' : 'Quiz' }
function editorRoute(object) {
  return { name: object.type === 'flashcard' ? 'flashcard-editor' : 'quiz-editor', params: { courseId: course.value.id, objectId: object.id } }
}
function generationLabel(object) {
  const status = object.generation?.status
  if (status === 'queued') return 'Đang chờ xử lý'
  if (status === 'generating') return `Đang tạo ${typeLabel(object.type)}`
  if (status === 'failed') return 'Tạo học liệu thất bại'
  if (object.status === 'published') return 'Đã xuất bản'
  if (object.status === 'archived') return 'Đã lưu trữ'
  return 'Bản nháp'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [courseResult, objectResult, documentResult] = await Promise.all([
      courseService.get(route.params.courseId),
      learningObjectService.list(route.params.courseId),
      documentService.list(route.params.courseId),
    ])
    course.value = courseResult.course
    objects.value = objectResult.learning_objects || []
    documents.value = documentResult.documents || []
  } catch (requestError) { error.value = requestError.message || 'Không thể tải thư viện học liệu.' }
  finally { loading.value = false }
}

async function pollPending() {
  const pending = objects.value.filter((object) => ['queued', 'generating'].includes(object.generation?.status))
  if (!pending.length) return
  await Promise.all(pending.map(async (object) => {
    try {
      const result = await learningObjectService.get(route.params.courseId, object.id)
      const updated = result.learning_object
      const index = objects.value.findIndex((item) => item.id === object.id)
      if (index !== -1) objects.value[index] = updated
      if (updated.generation?.status === 'completed' && activeObjectId.value === object.id) {
        activeObjectId.value = null
        toast.show(`${typeLabel(updated.type)} đã được tạo. Hãy kiểm tra nội dung trước khi xuất bản.`)
        await router.push(editorRoute(updated))
      } else if (updated.generation?.status === 'failed' && activeObjectId.value === object.id) {
        activeObjectId.value = null
        toast.show(updated.generation.error_message || 'Không thể tạo học liệu.', 'error')
      }
    } catch { /* Polling retries on the next interval. */ }
  }))
}

function generationPayload(form, type) {
  const common = {
    title: form.title.trim() || null,
    topic: form.topic.trim(),
    difficulty: form.difficulty,
    top_k: Number(form.top_k),
    document_ids: form.document_ids.map(Number),
    request_id: crypto.randomUUID(),
  }
  return type === 'flashcard'
    ? { ...common, card_count: Number(form.card_count), max_pages: Number(form.max_pages) }
    : { ...common, question_count: Number(form.question_count), passing_score: Number(form.passing_score), time_limit_minutes: form.time_limit_minutes ? Number(form.time_limit_minutes) : null }
}

async function generate() {
  const form = activeType.value === 'flashcard' ? flashcardForm : quizForm
  error.value = ''
  if (form.topic.trim().length < 3) { error.value = 'Chủ đề cần có ít nhất 3 ký tự.'; return }
  submitting.value = true
  try {
    const payload = generationPayload(form, activeType.value)
    const result = activeType.value === 'flashcard'
      ? await learningObjectService.generateFlashcards(route.params.courseId, payload)
      : await learningObjectService.generateQuiz(route.params.courseId, payload)
    activeObjectId.value = result.learning_object.id
    objects.value.unshift(result.learning_object)
    toast.show(`Yêu cầu tạo ${typeLabel(activeType.value)} đã được đưa vào hàng đợi. Bạn có thể chuyển trang trong khi hệ thống xử lý.`)
    backgroundTasks.fetchTasks()
  } catch (requestError) { error.value = requestError.message || 'Không thể gửi yêu cầu tạo học liệu.' }
  finally { submitting.value = false }
}

async function retryGeneration(object) {
  retryingId.value = object.id
  try {
    const result = await learningObjectService.retryGeneration(route.params.courseId, object.id)
    const index = objects.value.findIndex((item) => item.id === object.id)
    if (index !== -1) objects.value[index] = result.learning_object
    activeObjectId.value = object.id
    toast.show(`Yêu cầu tạo lại ${typeLabel(object.type)} đã được đưa vào hàng đợi.`)
    backgroundTasks.fetchTasks()
  } catch (requestError) { toast.show(requestError.message || 'Không thể thử lại tác vụ.', 'error') }
  finally { retryingId.value = null }
}

async function removeObject() {
  if (!objectToDelete.value) return
  deleting.value = true
  try {
    const target = objectToDelete.value
    await learningObjectService.delete(route.params.courseId, target.id)
    objects.value = objects.value.filter((item) => item.id !== target.id)
    toast.show(`Đã xóa ${typeLabel(target.type)}.`)
    objectToDelete.value = null
  } catch (requestError) { toast.show(requestError.message || 'Không thể xóa học liệu.', 'error') }
  finally { deleting.value = false }
}

onMounted(async () => { await load(); pollTimer = window.setInterval(pollPending, 3500) })
onBeforeUnmount(() => window.clearInterval(pollTimer))
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'course-detail', params: { id: route.params.courseId } })">Quay lại khóa học</button>
  <AppState v-if="loading" type="loading" title="Đang tải học liệu" message="Đang lấy tài liệu nguồn và danh sách học liệu." />
  <AppState v-else-if="error && !course" type="error" title="Không thể mở thư viện học liệu" :message="error" action-label="Thử lại" @action="load" />

  <template v-else>
    <section class="page-heading">
      <div><span class="eyebrow">{{ course.code }} · BIÊN SOẠN HỌC LIỆU</span><h1>Thư viện học liệu</h1><p>Tạo, kiểm tra và xuất bản nội dung học tập từ tài liệu đã xử lý của khóa học.</p></div>
    </section>

    <div class="learning-tabs" role="tablist" aria-label="Loại học liệu">
      <button :class="{ active: activeType === 'quiz' }" role="tab" @click="activeType = 'quiz'">Quiz</button>
      <button :class="{ active: activeType === 'flashcard' }" role="tab" @click="activeType = 'flashcard'">Flashcard</button>
    </div>

    <section class="learning-panel">
      <header class="section-header"><div><h2>Tạo {{ typeLabel(activeType) }} mới</h2><p>Chọn chủ đề và tài liệu nguồn. Kết quả được lưu ở trạng thái bản nháp để giảng viên kiểm tra.</p></div></header>
      <form class="generation-form" @submit.prevent="generate">
        <template v-if="activeType === 'quiz'">
          <BaseInput v-model="quizForm.title" label="Tiêu đề (không bắt buộc)" placeholder="Ví dụ: Kiểm tra tuần 1" />
          <BaseInput v-model="quizForm.topic" label="Chủ đề" placeholder="Ví dụ: Ma trận nghịch đảo" required />
          <label class="field"><span class="field__label">Độ khó</span><select v-model="quizForm.difficulty"><option value="easy">Dễ</option><option value="medium">Trung bình</option><option value="hard">Khó</option></select></label>
          <label class="field"><span class="field__label">Số câu hỏi</span><input v-model.number="quizForm.question_count" type="number" min="3" max="20" /></label>
          <label class="field"><span class="field__label">Số đoạn nguồn</span><input v-model.number="quizForm.top_k" type="number" min="3" max="15" /></label>
          <label class="field"><span class="field__label">Điểm đạt (%)</span><input v-model.number="quizForm.passing_score" type="number" min="0" max="100" /></label>
          <label class="field"><span class="field__label">Thời gian (phút)</span><input v-model.number="quizForm.time_limit_minutes" type="number" min="1" max="480" placeholder="Không giới hạn" /></label>
        </template>
        <template v-else>
          <BaseInput v-model="flashcardForm.title" label="Tiêu đề (không bắt buộc)" placeholder="Ví dụ: Khái niệm Đại số tuyến tính" />
          <BaseInput v-model="flashcardForm.topic" label="Chủ đề" placeholder="Ví dụ: Không gian vector và cơ sở" required />
          <label class="field"><span class="field__label">Độ khó</span><select v-model="flashcardForm.difficulty"><option value="easy">Dễ</option><option value="medium">Trung bình</option><option value="hard">Khó</option></select></label>
          <label class="field"><span class="field__label">Số thẻ</span><input v-model.number="flashcardForm.card_count" type="number" min="5" max="15" /></label>
          <label class="field"><span class="field__label">Số đoạn nguồn</span><input v-model.number="flashcardForm.top_k" type="number" min="3" max="15" /></label>
          <label class="field"><span class="field__label">Số trang nguồn tối đa</span><input v-model.number="flashcardForm.max_pages" type="number" min="1" max="10" /></label>
        </template>

        <fieldset class="document-picker">
          <legend>Tài liệu nguồn</legend>
          <p v-if="!processedDocuments.length" class="muted">Chưa có tài liệu sẵn sàng. Hãy xử lý ít nhất một tài liệu trước.</p>
          <label v-for="document in processedDocuments" :key="document.id">
            <input v-if="activeType === 'quiz'" v-model="quizForm.document_ids" type="checkbox" :value="document.id" />
            <input v-else v-model="flashcardForm.document_ids" type="checkbox" :value="document.id" />
            <span>{{ document.original_name }}</span>
          </label>
        </fieldset>
        <p v-if="error" class="form-error">{{ error }}</p>
        <BaseButton type="submit" :loading="submitting" :disabled="!processedDocuments.length || submitting">
          Tạo {{ typeLabel(activeType) }}
        </BaseButton>
      </form>
    </section>

    <section class="content-section">
      <header class="section-header"><div><h2>{{ typeLabel(activeType) }} trong khóa học</h2><p>Chỉ học liệu đã xuất bản mới hiển thị cho sinh viên.</p></div><span class="count-chip">{{ visibleObjects.length }} mục</span></header>
      <div v-if="visibleObjects.length" class="learning-object-list">
        <article v-for="object in visibleObjects" :key="object.id" class="learning-card">
          <div class="learning-card__header"><span class="status-chip" :class="`status-chip--${object.status}`">{{ generationLabel(object) }}</span><span v-if="object.current_version" class="object-spec">Phiên bản {{ object.current_version }} · {{ object.type === 'flashcard' ? `${object.total_cards} thẻ` : `${object.total_questions} câu` }}</span></div>
          <h3>{{ object.title }}</h3><p>{{ object.description || `Chưa có mô tả cho ${typeLabel(object.type)} này.` }}</p>
          <p v-if="object.generation?.status === 'failed'" class="form-error">{{ object.generation.error_message }}</p>
          <div class="table-actions">
            <RouterLink v-if="object.generation?.status === 'completed'" class="button button--secondary" :to="editorRoute(object)">Mở {{ typeLabel(object.type) }}</RouterLink>
            <BaseButton v-if="object.generation?.status === 'failed'" variant="secondary" :loading="retryingId === object.id" @click="retryGeneration(object)">Thử lại</BaseButton>
            <BaseButton variant="danger-ghost" @click="objectToDelete = object">Xóa</BaseButton>
          </div>
        </article>
      </div>
      <AppState v-else :title="`Chưa có ${typeLabel(activeType)}`" message="Sử dụng biểu mẫu phía trên để tạo học liệu đầu tiên." />
    </section>

    <AppModal v-model="objectToDelete" title="Xác nhận xóa học liệu" confirm-label="Xóa vĩnh viễn" :danger="true" :loading="deleting" @confirm="removeObject">
      <p>Bạn có chắc chắn muốn xóa <strong>{{ objectToDelete?.title }}</strong>? Dữ liệu liên quan không thể khôi phục.</p>
    </AppModal>
  </template>
</template>

<style scoped>
.learning-tabs { display: flex; gap: 2px; margin-bottom: 18px; border-bottom: 1px solid #cbd5e1; }
.learning-tabs button { padding: 11px 20px; border: 0; border-bottom: 3px solid transparent; background: transparent; color: var(--cds-text-secondary); font-weight: 700; cursor: pointer; }
.learning-tabs button.active { border-bottom-color: #0056d2; color: #0056d2; }
.object-spec { color: var(--cds-text-secondary); font-size: .78rem; }
</style>
