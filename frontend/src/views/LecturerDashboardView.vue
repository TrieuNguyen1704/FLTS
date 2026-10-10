<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import CourseCard from '../components/CourseCard.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'
import { learningObjectService } from '../services/learningObjectService'

const router = useRouter()
const courses = ref([])
const documentCount = ref(0)
const quizCount = ref(0)
const loading = ref(true)
const error = ref('')
const recentCourses = computed(() => courses.value.slice(0, 3))

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    courses.value = (await courseService.list()).courses
    const [documents, quizzes] = await Promise.all([
      Promise.all(courses.value.map((course) => documentService.list(course.id))),
      Promise.all(courses.value.map((course) => learningObjectService.list(course.id))),
    ])
    documentCount.value = documents.reduce((total, result) => total + result.documents.length, 0)
    quizCount.value = quizzes.reduce((total, result) => total + (result.learning_objects?.length || 0), 0)
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải dữ liệu bảng điều khiển.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <section class="page-heading">
    <div>
      <span class="eyebrow">KHÔNG GIAN GIẢNG VIÊN</span>
      <h1>Tổng quan</h1>
      <p>Theo dõi tiến độ, tài liệu học tập và bài kiểm tra Quiz trong các khóa học bạn phụ trách.</p>
    </div>
    <BaseButton @click="router.push({ name: 'course-management' })">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      Tạo khóa học
    </BaseButton>
  </section>

  <section v-if="loading" class="summary-grid">
    <div v-for="index in 3" :key="index" class="summary-card skeleton" />
  </section>

  <template v-else-if="!error">
    <section class="summary-grid">
      <article class="summary-card">
        <div class="summary-card__header">
          <span class="summary-card__label">Khóa học phụ trách</span>
          <div class="summary-card__icon-box">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            </svg>
          </div>
        </div>
        <strong>{{ courses.length }}</strong>
        <small>Khóa học đang hoạt động</small>
      </article>

      <article class="summary-card">
        <div class="summary-card__header">
          <span class="summary-card__label">Tài liệu đã nạp</span>
          <div class="summary-card__icon-box">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <polyline points="10 9 9 9 8 9" />
            </svg>
          </div>
        </div>
        <strong>{{ documentCount }}</strong>
        <small>Tài liệu giáo trình đã xử lý RAG</small>
      </article>

      <article class="summary-card">
        <div class="summary-card__header">
          <span class="summary-card__label">Học liệu Quiz</span>
          <div class="summary-card__icon-box">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 11l3 3L22 4" />
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
          </div>
        </div>
        <strong>{{ quizCount }}</strong>
        <small>Bộ câu hỏi trắc nghiệm tự động</small>
      </article>
    </section>

    <section class="content-section">
      <header class="section-header">
        <div>
          <h2>Khóa học gần đây</h2>
          <p>Mở khóa học để quản lý học liệu và tài liệu nguồn.</p>
        </div>
        <RouterLink :to="{ name: 'course-management' }">Xem tất cả khóa học →</RouterLink>
      </header>

      <div v-if="recentCourses.length" class="course-grid">
        <CourseCard v-for="course in recentCourses" :key="course.id" :course="course">
          <RouterLink class="inline-link" :to="{ name: 'course-detail', params: { id: course.id } }">
            Mở khóa học →
          </RouterLink>
        </CourseCard>
      </div>

      <AppState
        v-else
        title="Chưa có khóa học nào"
        message="Hãy tạo khóa học đầu tiên để bắt đầu lưu trữ tài liệu giảng dạy."
        action-label="Tạo khóa học"
        @action="router.push({ name: 'course-management' })"
      />
    </section>
  </template>

  <AppState
    v-else
    type="error"
    title="Không thể tải bảng điều khiển"
    :message="error"
    action-label="Thử lại"
    @action="loadDashboard"
  />
</template>

<style scoped>
.summary-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.summary-card__icon-box {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: var(--cds-radius-md);
  background: var(--cds-blue-tint);
  color: var(--cds-blue-primary);
}
</style>
