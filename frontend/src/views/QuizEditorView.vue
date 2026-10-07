<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import MathText from '../components/MathText.vue'
import { learningObjectService } from '../services/learningObjectService'
import { toast } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const object = ref(null)
const loading = ref(true)
const saving = ref(false)
const publishing = ref(false)
const deleting = ref(false)
const error = ref('')
const form = reactive({ title: '', description: '', passing_score: 60, time_limit_minutes: null, questions: [] })

function hydrate(value) {
  object.value = value
  form.title = value.title
  form.description = value.description || ''
  form.passing_score = value.quiz.passing_score
  form.time_limit_minutes = value.quiz.time_limit_minutes
  form.questions = value.quiz.questions.map((question) => ({
    question_text: question.question_text,
    explanation: question.explanation || '',
    options: question.options.map((option) => option.option_text),
    correct_index: question.options.findIndex((option) => option.is_correct),
    citations: question.citations || [],
  }))
}

async function load() {
  loading.value = true
  error.value = ''
  try { hydrate((await learningObjectService.get(route.params.courseId, route.params.objectId)).learning_object) }
  catch (requestError) { error.value = requestError.message || 'Không thể tải Quiz.' }
  finally { loading.value = false }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const result = await learningObjectService.update(route.params.courseId, route.params.objectId, {
      title: form.title.trim(), description: form.description.trim() || null,
      passing_score: Number(form.passing_score), time_limit_minutes: form.time_limit_minutes ? Number(form.time_limit_minutes) : null,
      questions: form.questions.map((question) => ({ ...question, correct_index: Number(question.correct_index) })),
    })
    hydrate(result.learning_object)
    toast.show('Đã lưu bản chỉnh sửa và tạo phiên bản mới.')
  } catch (requestError) { error.value = requestError.message || 'Không thể lưu Quiz.' }
  finally { saving.value = false }
}

async function publish() {
  if (!window.confirm('Xuất bản Quiz này cho Sinh viên? Sau khi xuất bản, nội dung sẽ không thể chỉnh sửa.')) return
  publishing.value = true
  try { hydrate((await learningObjectService.publish(route.params.courseId, route.params.objectId)).learning_object); toast.show('Quiz đã được xuất bản cho Sinh viên.') }
  catch (requestError) { error.value = requestError.message || 'Không thể xuất bản Quiz.' }
  finally { publishing.value = false }
}

async function archive() {
  if (!window.confirm('Lưu trữ Quiz này? Sinh viên sẽ không còn nhìn thấy Quiz.')) return
  try { hydrate((await learningObjectService.archive(route.params.courseId, route.params.objectId)).learning_object); toast.show('Quiz đã được lưu trữ.') }
  catch (requestError) { error.value = requestError.message || 'Không thể lưu trữ Quiz.' }
}

async function removeQuiz() {
  if (!window.confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn Quiz "${object.value.title}"? Toàn bộ câu hỏi, đáp án và kết quả làm bài của sinh viên sẽ bị xóa vĩnh viễn.`)) return
  deleting.value = true
  try {
    await learningObjectService.delete(route.params.courseId, route.params.objectId)
    toast.show('Đã xóa Quiz thành công.')
    router.push({ name: 'course-detail', params: { id: route.params.courseId }, query: { tab: 'quizzes' } })
  } catch (requestError) {
    error.value = requestError.message || 'Không thể xóa Quiz.'
  } finally {
    deleting.value = false
  }
}

function statusLabel(status) {
  if (status === 'draft') return 'Bản nháp'
  if (status === 'published') return 'Đã xuất bản'
  if (status === 'archived') return 'Đã lưu trữ'
  return status
}

onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'learning-objects', params: { courseId: route.params.courseId } })">Quay lại danh sách Quiz</button>
  <AppState v-if="loading" type="loading" title="Đang tải Quiz" message="Đang lấy nội dung và lịch sử phiên bản." />
  <AppState v-else-if="error && !object" type="error" title="Không thể mở Quiz" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading quiz-heading">
      <div><h1>{{ object.title }}</h1><p>Kiểm tra câu hỏi, đáp án, giải thích và nguồn trước khi xuất bản.</p></div>
      <div class="table-actions">
        <span class="status-chip" :class="`status-chip--${object.status}`">{{ statusLabel(object.status) }}</span>
        <BaseButton v-if="object.status === 'draft'" :loading="publishing" @click="publish">Phê duyệt và xuất bản</BaseButton>
        <BaseButton v-if="object.status !== 'archived'" variant="secondary" @click="archive">Lưu trữ</BaseButton>
        <BaseButton variant="danger-ghost" :loading="deleting" @click="removeQuiz">Xóa Quiz</BaseButton>
      </div>
    </section>
    <form class="quiz-editor" @submit.prevent="save">
      <div class="learning-panel">
        <BaseInput v-model="form.title" label="Tiêu đề Quiz" :disabled="object.status !== 'draft'" required />
        <label class="field"><span class="field__label">Mô tả</span><textarea v-model="form.description" :disabled="object.status !== 'draft'" /></label>
        <div class="parameter-row"><label class="field"><span class="field__label">Điểm đạt (%)</span><input v-model.number="form.passing_score" type="number" min="0" max="100" :disabled="object.status !== 'draft'" /></label><label class="field"><span class="field__label">Thời gian (phút)</span><input v-model.number="form.time_limit_minutes" type="number" min="1" max="480" :disabled="object.status !== 'draft'" /></label></div>
      </div>
      <article v-for="(question, questionIndex) in form.questions" :key="questionIndex" class="question-card">
        <header><strong>Câu {{ questionIndex + 1 }}</strong><span>{{ question.citations.length }} nguồn dẫn</span></header>
        <label class="field"><span class="field__label">Câu hỏi</span><textarea v-model="question.question_text" :disabled="object.status !== 'draft'" /></label>
        <div v-if="question.question_text" class="math-preview"><small>Hiển thị thực tế:</small><MathText :text="question.question_text" /></div>
        <div class="option-editor">
          <div v-for="(_, optionIndex) in question.options" :key="optionIndex" class="option-field-wrap">
            <label class="field"><span class="field__label">Lựa chọn {{ String.fromCharCode(65 + optionIndex) }}</span><input v-model="question.options[optionIndex]" :disabled="object.status !== 'draft'" /></label>
            <div v-if="question.options[optionIndex]" class="option-preview"><MathText :text="question.options[optionIndex]" /></div>
          </div>
        </div>
        <label class="field"><span class="field__label">Đáp án đúng</span><select v-model.number="question.correct_index" :disabled="object.status !== 'draft'"><option v-for="(_, optionIndex) in question.options" :key="optionIndex" :value="optionIndex">Lựa chọn {{ String.fromCharCode(65 + optionIndex) }}</option></select></label>
        <label class="field"><span class="field__label">Giải thích</span><textarea v-model="question.explanation" :disabled="object.status !== 'draft'" /></label>
        <div v-if="question.explanation" class="math-preview"><small>Hiển thị thực tế:</small><MathText :text="question.explanation" /></div>
        <ul class="citation-list"><li v-for="citation in question.citations" :key="citation.vector_id">{{ citation.document_name || 'Tài liệu nguồn' }} · {{ citation.source_locator || citation.vector_id }}</li></ul>
      </article>
      <p v-if="error" class="form-error">{{ error }}</p>
      <BaseButton v-if="object.status === 'draft'" type="submit" :loading="saving">Lưu thay đổi thành phiên bản mới</BaseButton>
    </form>
    <section class="content-section"><header class="section-header"><div><h2>Lịch sử phiên bản</h2><p>Mỗi lần lưu chỉnh sửa sẽ tạo một phiên bản mới.</p></div></header><div class="version-list"><span v-for="version in object.versions" :key="version.id" class="count-chip">Phiên bản {{ version.version_number }}</span></div></section>
  </template>
</template>

<style scoped>
.math-preview {
  margin-top: 4px;
  margin-bottom: 8px;
  padding: 6px 12px;
  background: #f8fafc;
  border-left: 3px solid #3b82f6;
  border-radius: 4px;
  font-size: 0.92rem;
  color: #1e293b;
}

.math-preview small {
  display: block;
  font-size: 0.74rem;
  color: #64748b;
  margin-bottom: 2px;
  font-weight: 500;
}

.option-field-wrap {
  display: flex;
  flex-direction: column;
}

.option-preview {
  margin-top: 2px;
  margin-bottom: 6px;
  padding: 4px 10px;
  background: #f1f5f9;
  border-radius: 4px;
  font-size: 0.88rem;
  color: #334155;
}
</style>
