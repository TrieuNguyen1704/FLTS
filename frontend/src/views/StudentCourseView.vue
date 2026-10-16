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
      <div class="course-hero__info"><span class="eyebrow">{{ course.code }}</span><h1>{{ course.name }}</h1><p class="course-hero__desc">{{ course.description || 'Học và ôn tập với các nội dung do giảng viên xuất bản.' }}</p></div>
      <div v-if="course.lecturer" class="course-hero__meta"><div class="meta-item"><span>Giảng viên hướng dẫn</span><strong>{{ course.lecturer.name }}</strong></div></div>
    </section>

    <section class="content-section">
      <header class="section-header"><div><h2>Flashcard ôn tập</h2><p>Lật thẻ, tự đánh giá và ôn lại các khái niệm trọng tâm.</p></div><span class="count-chip">{{ flashcards.length }} bộ</span></header>
      <div v-if="flashcards.length" class="learning-object-grid">
        <article v-for="object in flashcards" :key="object.id" class="learning-card">
          <div class="learning-card__header"><span class="status-chip status-chip--published">Đã xuất bản</span><span class="object-spec">{{ object.total_cards }} thẻ · Phiên bản {{ object.current_version }}</span></div>
          <h3>{{ object.title }}</h3><p>{{ object.description || 'Bộ thẻ ôn tập kiến thức của khóa học.' }}</p>
          <RouterLink class="button button--full" :to="{ name: 'student-flashcards', params: { courseId: course.id, objectId: object.id } }">Mở Flashcard</RouterLink>
        </article>
      </div>
      <AppState v-else title="Chưa có Flashcard" message="Giảng viên chưa xuất bản bộ Flashcard cho khóa học này." />
    </section>

    <section class="content-section">
      <header class="section-header"><div><h2>Quiz tự đánh giá</h2><p>Làm bài nhiều lần để củng cố kiến thức; hệ thống lưu điểm mới nhất và cao nhất.</p></div><span class="count-chip">{{ quizzes.length }} bài</span></header>
      <div v-if="quizzes.length" class="learning-object-grid">
        <article v-for="object in quizzes" :key="object.id" class="learning-card">
          <div class="learning-card__header"><span class="status-chip status-chip--published">Sẵn sàng làm bài</span><span class="object-spec">{{ object.total_questions }} câu · Phiên bản {{ object.current_version }}</span></div>
          <h3>{{ object.title }}</h3><p>{{ object.description || 'Bài kiểm tra trắc nghiệm bám sát nội dung khóa học.' }}</p>
          <RouterLink class="button button--full" :to="{ name: 'quiz-attempt', params: { courseId: course.id, objectId: object.id } }">Bắt đầu làm bài</RouterLink>
        </article>
      </div>
      <AppState v-else title="Chưa có Quiz" message="Giảng viên chưa xuất bản bài Quiz cho khóa học này." />
    </section>
  </template>
</template>

<style scoped>
.object-spec { color: var(--cds-text-secondary); font-size: .76rem; }
.learning-card h3 { margin: 0 0 8px; font-size: 1.05rem; color: var(--cds-text-primary); }
.learning-card p { flex: 1; margin: 0 0 16px; color: var(--cds-text-secondary); font-size: .85rem; line-height: 1.5; }
</style>
