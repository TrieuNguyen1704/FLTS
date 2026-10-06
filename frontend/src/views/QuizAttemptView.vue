<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import MathText from '../components/MathText.vue'
import { learningObjectService } from '../services/learningObjectService'

const route = useRoute()
const router = useRouter()
const object = ref(null)
const quiz = ref(null)
const attempt = ref(null)
const result = ref(null)
const history = ref({ attempts: [], latest_score: null, best_score: null })
const answers = reactive({})
const loading = ref(true)
const working = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [objectResult, historyResult] = await Promise.all([
      learningObjectService.get(route.params.courseId, route.params.objectId),
      learningObjectService.attemptHistory(route.params.courseId, route.params.objectId),
    ])
    object.value = objectResult.learning_object
    history.value = historyResult
  } catch (requestError) { error.value = requestError.message || 'Không thể tải Quiz.' }
  finally { loading.value = false }
}

async function start() {
  working.value = true
  error.value = ''
  result.value = null
  try {
    const response = await learningObjectService.startAttempt(route.params.courseId, route.params.objectId)
    attempt.value = response.attempt
    quiz.value = response.quiz
    Object.keys(answers).forEach((key) => delete answers[key])
  } catch (requestError) { error.value = requestError.message || 'Không thể bắt đầu lượt làm bài.' }
  finally { working.value = false }
}

async function submit() {
  working.value = true
  error.value = ''
  try {
    const payload = quiz.value.questions.map((question) => ({ question_id: question.id, selected_option_id: answers[question.id] ? Number(answers[question.id]) : null }))
    result.value = await learningObjectService.submitAttempt(route.params.courseId, route.params.objectId, attempt.value.id, payload)
    attempt.value = result.value.attempt
    history.value = await learningObjectService.attemptHistory(route.params.courseId, route.params.objectId)
  } catch (requestError) { error.value = requestError.message || 'Không thể nộp bài Quiz.' }
  finally { working.value = false }
}

function feedbackFor(questionId) { return result.value?.results?.find((item) => item.question_id === questionId) }
onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'student-course', params: { courseId: route.params.courseId } })">Quay lại khóa học</button>
  <AppState v-if="loading" type="loading" title="Đang tải Quiz" message="Đang kiểm tra quyền truy cập và lịch sử làm bài." />
  <AppState v-else-if="error && !object" type="error" title="Không thể mở Quiz" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading"><div><h1>{{ object.title }}</h1><p>{{ object.description || 'Bài kiểm tra kiến thức của khóa học.' }}</p></div></section>
    <section class="summary-grid attempt-summary"><article class="summary-card"><span class="summary-card__label">Điểm mới nhất</span><strong>{{ history.latest_score ?? '—' }}</strong><small>trên 100</small></article><article class="summary-card"><span class="summary-card__label">Điểm cao nhất</span><strong>{{ history.best_score ?? '—' }}</strong><small>trên 100</small></article><article class="summary-card"><span class="summary-card__label">Số lần hoàn thành</span><strong>{{ history.attempts.length }}</strong><small>được lưu đầy đủ</small></article></section>
    <section v-if="!attempt" class="learning-panel"><h2>Sẵn sàng làm bài?</h2><p class="muted">Bạn có thể làm lại nhiều lần. Đáp án và giải thích chỉ hiển thị sau khi nộp bài.</p><BaseButton :loading="working" @click="start">Bắt đầu lượt làm mới</BaseButton></section>
    <form v-else class="quiz-attempt" @submit.prevent="submit">
      <article v-for="(question, questionIndex) in quiz.questions" :key="question.id" class="question-card" :class="{ 'question-card--correct': feedbackFor(question.id)?.is_correct, 'question-card--incorrect': result && !feedbackFor(question.id)?.is_correct }">
        <header><strong>Câu {{ questionIndex + 1 }}</strong><span v-if="result">{{ feedbackFor(question.id)?.is_correct ? 'Đúng' : 'Chưa đúng' }}</span></header>
        <h3><MathText :text="question.question_text" /></h3>
        <label v-for="option in question.options" :key="option.id" class="quiz-option">
          <input v-model="answers[question.id]" type="radio" :name="`question-${question.id}`" :value="option.id" :disabled="Boolean(result)" />
          <span><MathText :text="option.option_text" /> <strong v-if="result && feedbackFor(question.id)?.correct_option_id === option.id" class="correct-answer-label">(Đáp án đúng)</strong></span>
        </label>
        <div v-if="result" class="quiz-feedback">
          <p><strong>Giải thích:</strong> <MathText :text="feedbackFor(question.id)?.explanation" /></p>
          <ul class="citation-list"><li v-for="citation in feedbackFor(question.id)?.citations || []" :key="citation.vector_id">{{ citation.document_name || 'Tài liệu nguồn' }} · {{ citation.source_locator || citation.vector_id }}</li></ul>
        </div>
      </article>
      <p v-if="error" class="form-error">{{ error }}</p>
      <BaseButton v-if="!result" type="submit" :loading="working">Nộp bài và xem kết quả</BaseButton>
    </form>
    <section v-if="result" class="result-banner"><div><p class="eyebrow">KẾT QUẢ LƯỢT {{ result.attempt.attempt_number }}</p><h2>{{ result.latest_score }} / 100</h2><p>Đúng {{ result.attempt.total_correct }} câu. Điểm cao nhất hiện tại: {{ result.best_score }}.</p></div><BaseButton :loading="working" @click="start">Làm lại Quiz</BaseButton></section>
  </template>
</template>
