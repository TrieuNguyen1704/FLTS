<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'
import { learningObjectService } from '../services/learningObjectService'
import { toast } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const course = ref(null)
const documents = ref([])
const objects = ref([])
const loading = ref(true)
const generating = ref(false)
const error = ref('')
const form = reactive({ title: '', topic: '', difficulty: 'medium', question_count: 5, top_k: 8, passing_score: 60, time_limit_minutes: null, document_ids: [] })
const processedDocuments = computed(() => documents.value.filter((document) => document.processing_status === 'processed'))

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
    error.value = requestError.message || 'Không thể tải khu vực học liệu.'
  } finally {
    loading.value = false
  }
}

async function generateQuiz() {
  error.value = ''
  if (form.topic.trim().length < 3) {
    error.value = 'Chủ đề cần có ít nhất 3 ký tự.'
    return
  }
  generating.value = true
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
    })
    toast.show('Quiz draft đã được tạo từ các nguồn RAG. Hãy kiểm tra trước khi xuất bản.')
    await router.push({ name: 'quiz-editor', params: { courseId: route.params.courseId, objectId: result.learning_object.id } })
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tạo Quiz lúc này.'
  } finally {
    generating.value = false
  }
}

onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'course-detail', params: { id: route.params.courseId } })">← Quay lại khóa học</button>
  <AppState v-if="loading" type="loading" title="Đang tải học liệu" message="Đang lấy Quiz và nguồn tài liệu đã xử lý." />
  <AppState v-else-if="error && !course" type="error" title="Không thể mở học liệu" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading">
      <div>
        <p class="eyebrow">{{ course.code }} · QUIZ LEARNING OBJECTS</p>
        <h1>Tạo và quản lý Quiz</h1>
        <p>Quiz được sinh từ các đoạn tài liệu đã lập chỉ mục. Mọi bản sinh đều bắt đầu ở trạng thái draft và cần Giảng viên kiểm tra.</p>
      </div>
    </section>

    <section class="learning-panel">
      <header class="section-header">
        <div><h2>Tạo Quiz mới</h2><p>Chọn chủ đề, độ khó và số câu hỏi. Không chọn tài liệu nghĩa là tìm trong toàn khóa học.</p></div>
      </header>
      <form class="generation-form" @submit.prevent="generateQuiz">
        <BaseInput v-model="form.title" label="Tiêu đề tùy chọn" placeholder="Để trống để AI đề xuất tiêu đề" />
        <BaseInput v-model="form.topic" label="Chủ đề" placeholder="Ví dụ: Kinh tế thị trường định hướng xã hội chủ nghĩa" required />
        <label class="field"><span class="field__label">Độ khó</span><select v-model="form.difficulty"><option value="easy">Dễ</option><option value="medium">Trung bình</option><option value="hard">Khó</option></select></label>
        <label class="field"><span class="field__label">Số câu hỏi</span><input v-model.number="form.question_count" type="number" min="3" max="20" /></label>
        <label class="field"><span class="field__label">Top-K nguồn</span><input v-model.number="form.top_k" type="number" min="3" max="15" /></label>
        <label class="field"><span class="field__label">Điểm đạt (%)</span><input v-model.number="form.passing_score" type="number" min="0" max="100" /></label>
        <label class="field"><span class="field__label">Thời gian (phút, không bắt buộc)</span><input v-model.number="form.time_limit_minutes" type="number" min="1" max="480" /></label>
        <fieldset class="document-picker">
          <legend>Tài liệu nguồn đã xử lý</legend>
          <p v-if="!processedDocuments.length" class="muted">Chưa có tài liệu processed. Hãy xử lý RAG tài liệu trước khi tạo Quiz.</p>
          <label v-for="document in processedDocuments" :key="document.id">
            <input v-model="form.document_ids" type="checkbox" :value="document.id" />
            <span>{{ document.original_name }}</span>
          </label>
        </fieldset>
        <p v-if="error" class="form-error">{{ error }}</p>
        <BaseButton type="submit" :loading="generating" :disabled="!processedDocuments.length">Tạo Quiz bằng RAG</BaseButton>
      </form>
    </section>

    <section class="content-section">
      <header class="section-header"><div><h2>Quiz của khóa học</h2><p>Draft chỉ dành cho Giảng viên; Student chỉ nhìn thấy bản published.</p></div><span class="count-chip">{{ objects.length }} học liệu</span></header>
      <div v-if="objects.length" class="learning-object-grid">
        <article v-for="object in objects" :key="object.id" class="learning-card">
          <div class="learning-card__header"><span class="status-chip" :class="`status-chip--${object.status}`">{{ object.status }}</span><span>Phiên bản {{ object.current_version }} · {{ object.total_questions }} câu</span></div>
          <h3>{{ object.title }}</h3><p>{{ object.description || 'Chưa có mô tả.' }}</p>
          <RouterLink class="button button--secondary" :to="{ name: 'quiz-editor', params: { courseId: course.id, objectId: object.id } }">Mở bản Quiz</RouterLink>
        </article>
      </div>
      <AppState v-else title="Chưa có Quiz" message="Tạo Quiz đầu tiên từ tài liệu đã xử lý của khóa học." />
    </section>
  </template>
</template>
