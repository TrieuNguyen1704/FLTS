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
      <h1>Tổng quan</h1>
      <p>Theo dõi khóa học và tài liệu của bạn.</p>
    </div>
    <BaseButton @click="router.push({ name: 'course-management' })">Tạo khóa học</BaseButton>
  </section>
  <section v-if="loading" class="summary-grid">
    <div v-for="index in 3" :key="index" class="summary-card skeleton" />
  </section>
  <template v-else-if="!error">
    <section class="summary-grid">
      <article class="summary-card">
        <span class="summary-card__label">Khóa học của tôi</span>
        <strong>{{ courses.length }}</strong>
        <small>Khóa học bạn phụ trách</small>
      </article>
      <article class="summary-card">
        <span class="summary-card__label">Tài liệu đã tải lên</span>
        <strong>{{ documentCount }}</strong>
        <small>Tài liệu giảng dạy đã lưu</small>
      </article>
      <article class="summary-card">
        <span class="summary-card__label">Bài kiểm tra Quiz</span>
        <strong>{{ quizCount }}</strong>
        <small>Bộ câu hỏi trắc nghiệm đã tạo</small>
      </article>
    </section>
    <section class="content-section">
      <header class="section-header">
        <div>
          <h2>Khóa học gần đây</h2>
          <p>Mở khóa học để quản lý học liệu và tài liệu nguồn.</p>
        </div>
        <RouterLink :to="{ name: 'course-management' }">Xem tất cả khóa học</RouterLink>
      </header>
      <div v-if="recentCourses.length" class="course-grid">
        <CourseCard v-for="course in recentCourses" :key="course.id" :course="course">
          <RouterLink class="inline-link" :to="{ name: 'course-detail', params: { id: course.id } }">
            Mở khóa học
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
