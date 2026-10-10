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
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải Quiz.'
  } finally {
    loading.value = false
  }
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
  } catch (requestError) {
    error.value = requestError.message || 'Không thể bắt đầu lượt làm bài.'
  } finally {
    working.value = false
  }
}

async function submit() {
  working.value = true
  error.value = ''
  try {
    const payload = quiz.value.questions.map((question) => ({
      question_id: question.id,
      selected_option_id: answers[question.id] ? Number(answers[question.id]) : null,
    }))
    result.value = await learningObjectService.submitAttempt(
      route.params.courseId,
      route.params.objectId,
      attempt.value.id,
      payload
    )
    attempt.value = result.value.attempt
    history.value = await learningObjectService.attemptHistory(route.params.courseId, route.params.objectId)
  } catch (requestError) {
    error.value = requestError.message || 'Không thể nộp bài Quiz.'
  } finally {
    working.value = false
  }
}

function feedbackFor(questionId) {
  return result.value?.results?.find((item) => item.question_id === questionId)
}

onMounted(load)
</script>

<template>
  <button
    class="back-link"
    @click="router.push({ name: 'student-course', params: { courseId: route.params.courseId } })"
  >
    ← Quay lại khóa học
  </button>

  <AppState
    v-if="loading"
    type="loading"
    title="Đang tải Quiz"
    message="Đang kiểm tra quyền truy cập và nạp lịch sử làm bài..."
  />
  <AppState
    v-else-if="error && !object"
    type="error"
    title="Không thể mở Quiz"
    :message="error"
    action-label="Thử lại"
    @action="load"
  />

  <template v-else>
    <!-- Header Heading -->
    <section class="page-heading">
      <div>
        <span class="eyebrow">BÀI KIỂM TRA TRẮC NGHIỆM</span>
        <h1>{{ object.title }}</h1>
        <p>{{ object.description || 'Bài kiểm tra kiến thức bám sát nội dung bài học theo mô hình lớp học đảo ngược.' }}</p>
      </div>
    </section>

    <!-- Score Summary Grid -->
    <section class="summary-grid attempt-summary">
      <article class="summary-card">
        <span class="summary-card__label">Điểm mới nhất</span>
        <strong>{{ history.latest_score !== null ? `${history.latest_score}` : '—' }}</strong>
        <small>Thang điểm 100</small>
      </article>

      <article class="summary-card">
        <span class="summary-card__label">Điểm cao nhất</span>
        <strong>{{ history.best_score !== null ? `${history.best_score}` : '—' }}</strong>
        <small>Thang điểm 100</small>
      </article>

      <article class="summary-card">
        <span class="summary-card__label">Số lần làm bài</span>
        <strong>{{ history.attempts.length }}</strong>
        <small>Được lưu lịch sử đầy đủ</small>
      </article>
    </section>

    <!-- Pre-attempt Ready Panel -->
    <section v-if="!attempt" class="assessment-start-card">
      <div class="assessment-start-card__content">
        <h2>Sẵn sàng bắt đầu làm bài?</h2>
        <p class="assessment-start-card__intro">
          Bài kiểm tra trắc nghiệm giúp bạn củng cố các khái niệm trọng tâm trước khi vào lớp.
        </p>
        <ul class="assessment-rules">
          <li>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Có thể làm lại nhiều lần để nâng cao điểm số và nắm vững kiến thức.</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Đáp án đúng cùng giải thích chi tiết và nguồn trích dẫn sẽ hiển thị ngay sau khi nộp bài.</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Các công thức toán học được hiển thị theo chuẩn KaTeX chính xác.</span>
          </li>
        </ul>
      </div>

      <div class="assessment-start-card__action">
        <BaseButton :loading="working" @click="start">
          Bắt đầu làm bài ngay →
        </BaseButton>
      </div>
    </section>

    <!-- Quiz Player Form -->
    <form v-else class="quiz-attempt" @submit.prevent="submit">
      <article
        v-for="(question, questionIndex) in quiz.questions"
        :key="question.id"
        class="question-card"
        :class="{
          'question-card--correct': feedbackFor(question.id)?.is_correct,
          'question-card--incorrect': result && !feedbackFor(question.id)?.is_correct,
        }"
      >
        <header class="question-header">
          <strong class="question-number">Câu hỏi {{ questionIndex + 1 }}</strong>
          <span
            v-if="result"
            class="question-result-badge"
            :class="feedbackFor(question.id)?.is_correct ? 'badge--correct' : 'badge--incorrect'"
          >
            {{ feedbackFor(question.id)?.is_correct ? '✓ Đúng' : '✗ Chưa chính xác' }}
          </span>
        </header>

        <h3 class="question-title">
          <MathText :text="question.question_text" />
        </h3>

        <div class="quiz-options-list">
          <label
            v-for="option in question.options"
            :key="option.id"
            class="quiz-option"
            :class="{
              'quiz-option--selected': answers[question.id] === option.id,
              'quiz-option--correct-answer': result && feedbackFor(question.id)?.correct_option_id === option.id,
            }"
          >
            <input
              v-model="answers[question.id]"
              type="radio"
              :name="`question-${question.id}`"
              :value="option.id"
              :disabled="Boolean(result)"
            />
            <span class="quiz-option__label">
              <MathText :text="option.option_text" />
              <strong
                v-if="result && feedbackFor(question.id)?.correct_option_id === option.id"
                class="correct-answer-label"
              >
                (Đáp án đúng)
              </strong>
            </span>
          </label>
        </div>

        <!-- Post-submission feedback with citations -->
        <div v-if="result" class="quiz-feedback">
          <div class="quiz-feedback__header">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="16" x2="12" y2="12" />
              <line x1="12" y1="8" x2="12.01" y2="8" />
            </svg>
            <strong>Giải thích chi tiết:</strong>
          </div>

          <p class="quiz-feedback__text">
            <MathText :text="feedbackFor(question.id)?.explanation" />
          </p>

          <div v-if="feedbackFor(question.id)?.citations?.length" class="quiz-citations">
            <span class="citations-label">Nguồn trích dẫn:</span>
            <ul class="citation-list">
              <li
                v-for="citation in feedbackFor(question.id)?.citations || []"
                :key="citation.vector_id"
              >
                <strong>{{ citation.document_name || 'Tài liệu giáo trình' }}</strong>
                <span> · {{ citation.source_locator || citation.vector_id }}</span>
              </li>
            </ul>
          </div>
        </div>
      </article>

      <p v-if="error" class="form-error">{{ error }}</p>

      <div class="quiz-actions-footer">
        <BaseButton v-if="!result" type="submit" :loading="working">
          Nộp bài và xem kết quả
        </BaseButton>
      </div>
    </form>

    <!-- Assessment Result Card -->
    <section v-if="result" class="result-banner">
      <div class="result-banner__content">
        <p class="eyebrow">KẾT QUẢ ĐÁNH GIÁ (LƯỢT {{ result.attempt.attempt_number }})</p>
        <h2>{{ result.latest_score }} / 100</h2>
        <p>
          Bạn đã trả lời đúng <strong>{{ result.attempt.total_correct }}</strong> câu hỏi.
          Điểm cao nhất ghi nhận: <strong>{{ result.best_score }} / 100</strong>.
        </p>
      </div>
      <BaseButton :loading="working" @click="start">
        Làm lại bài kiểm tra
      </BaseButton>
    </section>
  </template>
</template>

<style scoped>
.assessment-start-card {
  background: #ffffff;
  border: 1px solid var(--cds-border);
  border-radius: var(--cds-radius-md);
  padding: 32px 36px;
  box-shadow: var(--cds-shadow-sm);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 32px;
  flex-wrap: wrap;
  margin-top: 10px;
}

.assessment-start-card__content {
  flex: 1;
  min-width: 300px;
}

.assessment-start-card__content h2 {
  margin: 0 0 10px;
  font-size: 1.35rem;
  color: var(--cds-text-primary);
}

.assessment-start-card__intro {
  margin: 0 0 18px;
  color: var(--cds-text-secondary);
  font-size: 0.92rem;
}

.assessment-rules {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.assessment-rules li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--cds-text-primary);
  font-size: 0.88rem;
}

.assessment-rules li svg {
  color: var(--cds-success-text);
  flex-shrink: 0;
}

.question-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.question-number {
  font-size: 0.82rem;
  color: var(--cds-blue-primary);
  background: var(--cds-blue-tint);
  padding: 3px 10px;
  border-radius: var(--cds-radius-pill);
  font-weight: 700;
}

.question-result-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: var(--cds-radius-pill);
}

.badge--correct {
  background: var(--cds-success-bg);
  color: var(--cds-success-text);
}

.badge--incorrect {
  background: var(--cds-danger-bg);
  color: var(--cds-danger-text);
}

.question-title {
  margin: 0 0 18px;
  font-size: 1.08rem;
  line-height: 1.5;
  color: var(--cds-text-primary);
}

.quiz-options-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.quiz-option__label {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.quiz-option--correct-answer {
  border-color: var(--cds-success-border) !important;
  background: var(--cds-success-bg) !important;
}

.quiz-feedback__header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  color: var(--cds-blue-primary);
  font-size: 0.84rem;
}

.quiz-feedback__text {
  margin: 0 0 10px;
  color: var(--cds-text-primary);
  line-height: 1.6;
}

.quiz-citations {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed var(--cds-border);
}

.citations-label {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--cds-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.quiz-actions-footer {
  margin-top: 10px;
}

.result-banner__content p strong {
  color: var(--cds-text-primary);
}
</style>
