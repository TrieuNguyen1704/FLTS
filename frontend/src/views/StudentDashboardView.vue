<script setup>
import { onMounted, ref } from 'vue'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import CourseCard from '../components/CourseCard.vue'
import { courseService } from '../services/courseService'
import { toast } from '../stores/toast'

const courses = ref([])
const loading = ref(true)
const error = ref('')

// Join course by code modal
const showJoinModal = ref(false)
const joinCode = ref('')
const joinError = ref('')
const joining = ref(false)

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

function openJoinModal() {
  joinCode.value = ''
  joinError.value = ''
  showJoinModal.value = true
}

async function handleJoinCourse() {
  const code = joinCode.value.trim().toUpperCase()
  if (!code) {
    joinError.value = 'Vui lòng nhập mã ghi danh.'
    return
  }
  joinError.value = ''
  joining.value = true
  try {
    const res = await courseService.joinCourse(code)
    toast.show(res.message || 'Đã tham gia khóa học thành công.')
    showJoinModal.value = false
    await loadCourses()
  } catch (err) {
    joinError.value = err.message || 'Không thể tham gia khóa học.'
  } finally {
    joining.value = false
  }
}

onMounted(loadCourses)
</script>

<template>
  <section class="page-heading">
    <div>
      <h1>Khóa học của tôi</h1>
      <p>Danh sách khóa học bạn được cấp quyền truy cập hoặc đã ghi danh.</p>
    </div>
    <div>
      <BaseButton @click="openJoinModal">+ Tham gia bằng mã</BaseButton>
    </div>
  </section>
  <section v-if="loading" class="course-grid">
    <div v-for="index in 2" :key="index" class="course-card skeleton" />
  </section>
  <section v-else-if="error">
    <AppState type="error" title="Không thể tải danh sách khóa học" :message="error" action-label="Thử lại" @action="loadCourses" />
  </section>
  <section v-else-if="courses.length" class="course-grid">
    <CourseCard v-for="course in courses" :key="course.id" :course="course" compact>
      <RouterLink class="inline-link" :to="{ name: 'student-course', params: { courseId: course.id } }">Mở không gian học tập</RouterLink>
    </CourseCard>
  </section>
  <AppState
    v-else
    title="Chưa có khóa học nào"
    message="Bạn chưa ghi danh vào khóa học nào. Hãy sử dụng mã ghi danh do Giảng viên cung cấp để tham gia khóa học."
    action-label="Tham gia khóa học bằng mã"
    @action="openJoinModal"
  />

  <!-- MODAL: THAM GIA KHÓA HỌC BẰNG MÃ -->
  <AppModal
    v-model="showJoinModal"
    title="Tham gia khóa học bằng mã"
    confirm-label="Tham gia khóa học"
    :loading="joining"
    @confirm="handleJoinCourse"
  >
    <div class="join-modal-content">
      <p class="join-modal-description">
        Nhập mã ghi danh do Giảng viên cung cấp (ví dụ: <code>FLTS-ABC123</code>) để truy cập tài liệu và làm các bài kiểm tra trắc nghiệm của khóa học.
      </p>
      <BaseInput
        v-model="joinCode"
        label="Mã ghi danh"
        placeholder="FLTS-XXXXXX"
        :error="joinError"
        required
        @keyup.enter="handleJoinCourse"
      />
    </div>
  </AppModal>
</template>

<style scoped>
.page-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.join-modal-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.join-modal-description {
  color: #64748b;
  font-size: 0.88rem;
  line-height: 1.5;
  margin: 0;
}

.join-modal-description code {
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
  font-weight: 600;
  color: #2563eb;
}
</style>
