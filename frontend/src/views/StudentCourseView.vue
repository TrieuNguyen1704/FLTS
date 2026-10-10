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
    const [courseResult, objectResult] = await Promise.all([
      courseService.get(route.params.courseId),
      learningObjectService.list(route.params.courseId),
    ])
    course.value = courseResult.course
    objects.value = objectResult.learning_objects || []
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải không gian học tập.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'student-dashboard' })">
    ← Quay lại khóa học của tôi
  </button>

  <AppState
    v-if="loading"
    type="loading"
    title="Đang tải học liệu"
    message="Đang nạp các bài kiểm tra Quiz do Giảng viên xuất bản..."
  />
  <AppState
    v-else-if="error"
    type="error"
    title="Không thể mở khóa học"
    :message="error"
    action-label="Thử lại"
    @action="load"
  />

  <template v-else>
    <!-- Coursera Course Hero -->
    <section class="course-hero">
      <div class="course-hero__info">
        <span class="eyebrow">{{ course.code }}</span>
        <h1>{{ course.name }}</h1>
        <p class="course-hero__desc">
          {{ course.description || 'Chào mừng bạn đến với khóa học. Hãy hoàn thành các bài Quiz để củng cố kiến thức trước giờ lên lớp.' }}
        </p>
      </div>
      <div v-if="course.lecturer" class="course-hero__meta">
        <div class="meta-item">
          <span>Giảng viên hướng dẫn</span>
          <strong>{{ course.lecturer.name }}</strong>
        </div>
      </div>
    </section>

    <!-- Quiz Modules List -->
    <section class="content-section">
      <header class="section-header">
        <div>
          <h2>Bài tập & Quiz tự đánh giá</h2>
          <p>Làm bài kiểm tra nhiều lần để củng cố kiến thức. Hệ thống lưu điểm cao nhất và điểm mới nhất.</p>
        </div>
        <span class="count-chip">{{ objects.length }} Quiz đã mở</span>
      </header>

      <div v-if="objects.length" class="learning-object-grid">
        <article v-for="object in objects" :key="object.id" class="learning-card">
          <div class="learning-card__header">
            <span class="status-chip status-chip--published">Sẵn sàng làm bài</span>
            <span class="quiz-spec-tag">
              Phiên bản {{ object.current_version }} · {{ object.total_questions }} câu
            </span>
          </div>

          <h3 class="learning-card__title">{{ object.title }}</h3>
          <p class="learning-card__desc">
            {{ object.description || 'Bài kiểm tra trắc nghiệm bám sát nội dung giáo trình được AI trích xuất.' }}
          </p>

          <RouterLink
            class="button button--full"
            :to="{ name: 'quiz-attempt', params: { courseId: course.id, objectId: object.id } }"
          >
            Bắt đầu làm bài →
          </RouterLink>
        </article>
      </div>

      <AppState
        v-else
        title="Chưa có Quiz nào được xuất bản"
        message="Giảng viên đang biên soạn nội dung kiểm tra cho khóa học này. Vui lòng quay lại sau."
      />
    </section>
  </template>
</template>

<style scoped>
.quiz-spec-tag {
  color: var(--cds-text-secondary);
  font-size: 0.76rem;
}

.learning-card__title {
  margin: 0 0 8px;
  font-size: 1.05rem;
  color: var(--cds-text-primary);
  font-weight: 700;
}

.learning-card__desc {
  margin: 0 0 16px;
  font-size: 0.85rem;
  color: var(--cds-text-secondary);
  line-height: 1.5;
  flex: 1;
}
</style>
