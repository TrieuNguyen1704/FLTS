<script setup>
import { onMounted, ref } from 'vue'
import AppState from '../components/AppState.vue'
import CourseCard from '../components/CourseCard.vue'
import { courseService } from '../services/courseService'

const courses = ref([])
const loading = ref(true)
const error = ref('')

async function loadCourses() {
  loading.value = true
  error.value = ''
  try {
    courses.value = (await courseService.list()).courses
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải danh sách khóa học.'
  } finally {
    loading.value = false
  }
}

onMounted(loadCourses)
</script>

<template>
  <section class="page-heading">
    <div>
      <p class="eyebrow">KHÔNG GIAN HỌC TẬP</p>
      <h1>Khóa học của tôi</h1>
      <p>Danh sách các khóa học bạn đã đăng ký hoặc được cấp quyền truy cập học tập.</p>
    </div>
  </section>
  <section class="notice-banner">
    <strong>Lưu ý học tập</strong>
    <span>Sinh viên chỉ truy cập được các học liệu và tài liệu đã được Giảng viên phê duyệt và xuất bản chính thức.</span>
  </section>
  <section v-if="loading" class="course-grid">
    <div v-for="index in 2" :key="index" class="course-card skeleton" />
  </section>
  <section v-else-if="error">
    <AppState type="error" title="Không thể tải danh sách khóa học" :message="error" action-label="Thử lại" @action="loadCourses" />
  </section>
  <section v-else-if="courses.length" class="course-grid">
    <CourseCard v-for="course in courses" :key="course.id" :course="course" compact>
      <span class="course-card__access">Đã tham gia</span>
    </CourseCard>
  </section>
  <AppState
    v-else
    title="Chưa có khóa học nào"
    message="Giảng viên chưa phân quyền khóa học cho tài khoản này. Vui lòng liên hệ Giảng viên của bạn."
  />
</template>
