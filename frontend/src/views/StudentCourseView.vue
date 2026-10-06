<script setup>
import { onMounted, ref } from 'vue'
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

async function load() {
  loading.value = true
  try {
    const [courseResult, objectResult] = await Promise.all([courseService.get(route.params.courseId), learningObjectService.list(route.params.courseId)])
    course.value = courseResult.course
    objects.value = objectResult.learning_objects
  } catch (requestError) { error.value = requestError.message || 'Không thể tải không gian học tập.' }
  finally { loading.value = false }
}
onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'student-dashboard' })">← Quay lại khóa học của tôi</button>
  <AppState v-if="loading" type="loading" title="Đang tải học liệu" message="Đang lấy các học liệu đã được Giảng viên xuất bản." />
  <AppState v-else-if="error" type="error" title="Không thể mở khóa học" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="course-hero"><div><p class="eyebrow">{{ course.code }}</p><h1>{{ course.name }}</h1><p>{{ course.description }}</p></div></section>
    <section class="notice-banner"><strong>Nội dung đã duyệt</strong><span>Trang này chỉ hiển thị học liệu đã được Giảng viên xuất bản. Draft và đáp án không được gửi tới Sinh viên.</span></section>
    <section class="content-section"><header class="section-header"><div><h2>Quiz đã xuất bản</h2><p>Làm bài nhiều lần và theo dõi điểm mới nhất cùng điểm cao nhất.</p></div><span class="count-chip">{{ objects.length }} Quiz</span></header>
      <div v-if="objects.length" class="learning-object-grid"><article v-for="object in objects" :key="object.id" class="learning-card"><div class="learning-card__header"><span class="status-chip status-chip--published">Đã xuất bản</span><span>Phiên bản {{ object.current_version }} · {{ object.total_questions }} câu</span></div><h3>{{ object.title }}</h3><p>{{ object.description || 'Quiz của khóa học.' }}</p><RouterLink class="button" :to="{ name: 'quiz-attempt', params: { courseId: course.id, objectId: object.id } }">Mở Quiz</RouterLink></article></div>
      <AppState v-else title="Chưa có Quiz được xuất bản" message="Giảng viên chưa xuất bản học liệu cho khóa học này." />
    </section>
  </template>
</template>
