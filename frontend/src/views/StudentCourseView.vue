<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import { courseService } from '../services/courseService'
import { learningObjectService } from '../services/learningObjectService'

const route = useRoute()
const router = useRouter()
const course = ref(null)
const objects = ref([])
const loading = ref(true)
const error = ref('')
const quizzes = computed(() => objects.value.filter((item) => item.type === 'quiz'))
const flashcards = computed(() => objects.value.filter((item) => item.type === 'flashcard'))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [courseResult, objectResult] = await Promise.all([
      courseService.get(route.params.courseId),
      learningObjectService.list(route.params.courseId),
    ])
    course.value = courseResult.course
    objects.value = objectResult.learning_objects || []
  } catch (requestError) { error.value = requestError.message || 'Không thể tải không gian học tập.' }
  finally { loading.value = false }
}

onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'student-dashboard' })">Quay lại khóa học của tôi</button>
  <AppState v-if="loading" type="loading" title="Đang tải học liệu" message="Đang lấy các học liệu đã được giảng viên xuất bản." />
  <AppState v-else-if="error" type="error" title="Không thể mở khóa học" :message="error" action-label="Thử lại" @action="load" />

  <template v-else>
    <section class="course-hero">
      <div class="course-hero__info">
        <span class="eyebrow">{{ course.code }} · KHÔNG GIAN HỌC TẬP</span>
        <h1>{{ course.name }}</h1>
        <p class="course-hero__desc">{{ course.description || 'Học và ôn tập với các nội dung do giảng viên xuất bản.' }}</p>
        <div class="hero-stats">
          <span class="hero-stat-badge">🎴 {{ flashcards.length }} bộ Flashcard</span>
          <span class="hero-stat-badge">📝 {{ quizzes.length }} bài Quiz trắc nghiệm</span>
        </div>
      </div>
      <div v-if="course.lecturer" class="course-hero__meta">
        <div class="meta-item">
          <span>Giảng viên hướng dẫn</span>
          <strong>{{ course.lecturer.name }}</strong>
        </div>
      </div>
    </section>

    <!-- SECTION 1: FLASHCARD ÔN TẬP -->
    <section class="content-section">
      <header class="section-header">
        <div>
          <h2>Flashcard ôn tập</h2>
          <p>Lật thẻ tương tác 3D, ghi nhớ thuật ngữ và xem tài liệu dẫn chứng từ giáo trình.</p>
        </div>
        <span class="count-chip">{{ flashcards.length }} bộ</span>
      </header>
      <div v-if="flashcards.length" class="learning-object-grid">
        <article v-for="object in flashcards" :key="object.id" class="learning-card learning-card--flashcard">
          <div class="learning-card__header">
            <span class="type-badge type-badge--flashcard">Flashcard</span>
            <span class="object-spec">{{ object.total_cards }} thẻ · Bản {{ object.current_version }}</span>
          </div>
          <h3>{{ object.title }}</h3>
          <p>{{ object.description || 'Bộ thẻ ghi nhớ thuật ngữ và khái niệm trọng tâm của môn học.' }}</p>
          <RouterLink class="button button--full" :to="{ name: 'student-flashcards', params: { courseId: course.id, objectId: object.id } }">
            Lật thẻ ôn tập →
          </RouterLink>
        </article>
      </div>
      <AppState v-else title="Chưa có Flashcard" message="Giảng viên chưa xuất bản bộ Flashcard cho khóa học này." />
    </section>

    <!-- SECTION 2: QUIZ TỰ ĐÁNH GIÁ -->
    <section class="content-section">
      <header class="section-header">
        <div>
          <h2>Quiz tự đánh giá</h2>
          <p>Làm bài trắc nghiệm nhiều lần để củng cố kiến thức; xem giải thích chi tiết và trích dẫn giáo trình.</p>
        </div>
        <span class="count-chip">{{ quizzes.length }} bài</span>
      </header>
      <div v-if="quizzes.length" class="learning-object-grid">
        <article v-for="object in quizzes" :key="object.id" class="learning-card learning-card--quiz">
          <div class="learning-card__header">
            <span class="type-badge type-badge--quiz">Quiz</span>
            <span class="object-spec">{{ object.total_questions }} câu · Bản {{ object.current_version }}</span>
          </div>
          <h3>{{ object.title }}</h3>
          <p>{{ object.description || 'Bài kiểm tra trắc nghiệm bám sát nội dung bài học.' }}</p>
          <RouterLink class="button button--full button--secondary" :to="{ name: 'quiz-attempt', params: { courseId: course.id, objectId: object.id } }">
            Bắt đầu làm bài →
          </RouterLink>
        </article>
      </div>
      <AppState v-else title="Chưa có Quiz" message="Giảng viên chưa xuất bản bài Quiz cho khóa học này." />
    </section>
  </template>
</template>

<style scoped>
.hero-stats {
  display: flex;
  gap: 10px;
  margin-top: 12px;
  flex-wrap: wrap;
}
.hero-stat-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: var(--cds-radius-pill);
  background: var(--cds-bg-subtle);
  border: 1px solid var(--cds-border);
  color: var(--cds-text-secondary);
  font-size: 0.8rem;
  font-weight: 600;
}
.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.type-badge--flashcard {
  background: #f5f3ff;
  color: #7c3aed;
  border: 1px solid #ddd6fe;
}
.type-badge--quiz {
  background: var(--cds-blue-tint);
  color: var(--cds-blue-primary);
  border: 1px solid #bfdbfe;
}
.object-spec { color: var(--cds-text-secondary); font-size: .76rem; }
.learning-card h3 { margin: 0 0 8px; font-size: 1.05rem; color: var(--cds-text-primary); }
.learning-card p { flex: 1; margin: 0 0 16px; color: var(--cds-text-secondary); font-size: .85rem; line-height: 1.5; }
</style>
