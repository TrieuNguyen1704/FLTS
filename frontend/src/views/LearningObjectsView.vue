<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
const loading = ref(true)
const submitting = ref(false)
const retryingId = ref(null)
const error = ref('')
const activeObjectId = ref(null)
let pollTimer = null
const form = reactive({ title: '', topic: '', difficulty: 'medium', question_count: 5, top_k: 8, passing_score: 60, time_limit_minutes: null, document_ids: [] })
const processedDocuments = computed(() => documents.value.filter((document) => document.processing_status === 'processed'))
const hasPendingGeneration = computed(() => objects.value.some((object) => ['queued', 'generating'].includes(object.generation?.status)))

function generationLabel(object) {
  const status = object.generation?.status
  if (status === 'queued') return 'Đang chờ xử lý'
  if (status === 'generating') return 'Đang tạo câu hỏi'
  if (status === 'failed') return 'Tạo Quiz thất bại'
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
    objects.value = objectResult.learning_objects
    documents.value = documentResult.documents
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải học liệu.'
  } finally {
    loading.value = false
  }
}

async function pollPending() {
  if (!hasPendingGeneration.value) return
  const pending = objects.value.filter((object) => ['queued', 'generating'].includes(object.generation?.status))
  await Promise.all(pending.map(async (object) => {
    try {
      const result = await learningObjectService.get(route.params.courseId, object.id)
      const index = objects.value.findIndex((item) => item.id === object.id)
      if (index !== -1) objects.value[index] = result.learning_object
      if (result.learning_object.generation?.status === 'completed' && activeObjectId.value === object.id) {
        activeObjectId.value = null
        toast.show('Quiz đã được tạo. Vui lòng kiểm tra nội dung trước khi xuất bản.')
        await router.push({ name: 'quiz-editor', params: { courseId: route.params.courseId, objectId: object.id } })
      }
      if (result.learning_object.generation?.status === 'failed' && activeObjectId.value === object.id) {
        activeObjectId.value = null
        toast.show(result.learning_object.generation.error_message || 'Không thể tạo Quiz.', 'error')
      }
    } catch {
      // A transient polling failure is retried automatically on the next interval.
    }
  }))
}

async function generateQuiz() {
  error.value = ''
  if (form.topic.trim().length < 3) {
    error.value = 'Chủ đề cần có ít nhất 3 ký tự.'
    return
  }
  submitting.value = true
  try {
    const result = await learningObjectService.generateQuiz(route.params.courseId, {
      title: form.title.trim() || null,
      topic: form.topic.trim(),
      difficulty: form.difficulty,
      question_count: Number(form.question_count),
      top_k: Number(form.top_k),
      passing_score: Number(form.passing_score),
      time_limit_minutes: form.time_limit_minutes ? Number(form.time_limit_minutes) : null,
      document_ids: form.document_ids.map(Number),
      request_id: crypto.randomUUID(),
    })
    activeObjectId.value = result.learning_object.id
    objects.value.unshift(result.learning_object)
    toast.show('Yêu cầu tạo Quiz đã được tiếp nhận. Bạn có thể tiếp tục làm việc trong khi hệ thống xử lý.')
    backgroundTasks.fetchTasks()
    await pollPending()
  } catch (requestError) {
    error.value = requestError.message || 'Không thể gửi yêu cầu tạo Quiz lúc này.'
  } finally {
    submitting.value = false
  }
}

async function retryGeneration(object) {
  retryingId.value = object.id
  try {
    const result = await learningObjectService.retryGeneration(route.params.courseId, object.id)
    const index = objects.value.findIndex((item) => item.id === object.id)
    if (index !== -1) objects.value[index] = result.learning_object
    activeObjectId.value = object.id
    toast.show('Yêu cầu tạo lại Quiz đã được đưa vào hàng đợi.')
    backgroundTasks.fetchTasks()
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tạo lại Quiz.', 'error')
  } finally {
    retryingId.value = null
  }
}

onMounted(async () => {
  await load()
  pollTimer = window.setInterval(pollPending, 3000)
})
onBeforeUnmount(() => window.clearInterval(pollTimer))
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'course-detail', params: { id: route.params.courseId } })">Quay lại khóa học</button>
  <AppState v-if="loading" type="loading" title="Đang tải học liệu" message="Vui lòng chờ trong giây lát." />
  <AppState v-else-if="error && !course" type="error" title="Không thể mở học liệu" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading">
      <div>
        <p class="eyebrow">{{ course.code }}</p>
        <h1>Quiz</h1>
        <p>Tạo câu hỏi từ tài liệu của khóa học, kiểm tra nội dung và xuất bản cho sinh viên.</p>
      </div>
    </section>

    <section class="learning-panel">
      <header class="section-header">
        <div><h2>Tạo Quiz mới</h2><p>Chọn chủ đề, độ khó, số câu hỏi và tài liệu nguồn.</p></div>
      </header>
      <form class="generation-form" @submit.prevent="generateQuiz">
        <BaseInput v-model="form.title" label="Tiêu đề (không bắt buộc)" placeholder="Nhập tiêu đề Quiz" />
        <BaseInput v-model="form.topic" label="Chủ đề" placeholder="Nhập chủ đề cần kiểm tra" required />
        <label class="field"><span class="field__label">Độ khó</span><select v-model="form.difficulty"><option value="easy">Dễ</option><option value="medium">Trung bình</option><option value="hard">Khó</option></select></label>
        <label class="field"><span class="field__label">Số câu hỏi</span><input v-model.number="form.question_count" type="number" min="3" max="20" /></label>
        <label class="field"><span class="field__label">Số nguồn tham khảo</span><input v-model.number="form.top_k" type="number" min="3" max="15" /></label>
        <label class="field"><span class="field__label">Điểm đạt (%)</span><input v-model.number="form.passing_score" type="number" min="0" max="100" /></label>
        <label class="field"><span class="field__label">Thời gian làm bài (phút)</span><input v-model.number="form.time_limit_minutes" type="number" min="1" max="480" placeholder="Không giới hạn" /></label>
        <fieldset class="document-picker">
          <legend>Tài liệu nguồn</legend>
          <p v-if="!processedDocuments.length" class="muted">Chưa có tài liệu sẵn sàng. Hãy xử lý ít nhất một tài liệu trước khi tạo Quiz.</p>
          <label v-for="document in processedDocuments" :key="document.id">
            <input v-model="form.document_ids" type="checkbox" :value="document.id" />
            <span>{{ document.original_name }}</span>
          </label>
        </fieldset>
        <p v-if="error" class="form-error">{{ error }}</p>
        <BaseButton type="submit" :loading="submitting" :disabled="!processedDocuments.length || submitting || hasPendingGeneration">{{ hasPendingGeneration ? 'Đang tạo Quiz' : 'Tạo Quiz' }}</BaseButton>
      </form>
    </section>

    <section class="content-section">
      <header class="section-header"><div><h2>Danh sách Quiz</h2><p>Chỉ Quiz đã xuất bản mới hiển thị cho sinh viên.</p></div><span>{{ objects.length }} Quiz</span></header>
      <div v-if="objects.length" class="learning-object-list">
        <article v-for="object in objects" :key="object.id" class="learning-card">
          <div class="learning-card__header"><strong>{{ generationLabel(object) }}</strong><span v-if="object.current_version">Phiên bản {{ object.current_version }} · {{ object.total_questions }} câu</span></div>
          <h3>{{ object.title }}</h3>
          <p>{{ object.description || 'Chưa có mô tả.' }}</p>
          <p v-if="object.generation?.status === 'failed'" class="form-error">{{ object.generation.error_message }}</p>
          <div class="table-actions">
            <RouterLink v-if="object.generation?.status === 'completed' || object.quiz" class="button button--secondary" :to="{ name: 'quiz-editor', params: { courseId: course.id, objectId: object.id } }">Mở Quiz</RouterLink>
            <BaseButton v-if="object.generation?.status === 'failed'" variant="secondary" :loading="retryingId === object.id" @click="retryGeneration(object)">Thử lại</BaseButton>
          </div>
        </article>
      </div>
      <AppState v-else title="Chưa có Quiz" message="Tạo Quiz đầu tiên từ tài liệu của khóa học." />
    </section>
  </template>
</template>
