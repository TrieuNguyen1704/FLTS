<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import CourseCard from '../components/CourseCard.vue'
import { courseService } from '../services/courseService'
import { toast } from '../stores/toast'

const router = useRouter()
const courses = ref([])
const loading = ref(true)
const error = ref('')
const showCreate = ref(false)
const saving = ref(false)
const form = reactive({ name: '', code: '', description: '' })
const formErrors = reactive({ name: '', code: '', description: '' })

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

function resetForm() {
  Object.assign(form, { name: '', code: '', description: '' })
  Object.keys(formErrors).forEach((key) => { formErrors[key] = '' })
}

function openCreate() {
  resetForm()
  showCreate.value = true
}

function validate() {
  formErrors.name = form.name.trim() ? '' : 'Vui lòng nhập tên khóa học.'
  formErrors.code = form.code.trim() ? '' : 'Vui lòng nhập mã khóa học.'
  return !formErrors.name && !formErrors.code
}

async function createCourse() {
  if (!validate()) return
  saving.value = true
  try {
    const { course } = await courseService.create({
      name: form.name.trim(),
      code: form.code.trim(),
      description: form.description.trim() || null
    })
    showCreate.value = false
    toast.show('Tạo khóa học mới thành công.')
    router.push({ name: 'course-detail', params: { id: course.id } })
  } catch (requestError) {
    const errors = requestError.errors || {}
    formErrors.name = errors.name?.[0] || ''
    formErrors.code = errors.code?.[0] || requestError.message
  } finally {
    saving.value = false
  }
}

onMounted(loadCourses)
</script>

<template>
  <section class="page-heading">
    <div>
      <p class="eyebrow">KHÔNG GIAN GIẢNG VIÊN</p>
      <h1>Quản lý khóa học</h1>
      <p>Tạo và quản lý các khóa học do bạn phụ trách giảng dạy.</p>
    </div>
    <BaseButton @click="openCreate">Tạo khóa học mới</BaseButton>
  </section>
  <section v-if="loading" class="course-grid">
    <div v-for="index in 3" :key="index" class="course-card skeleton" />
  </section>
  <section v-else-if="error">
    <AppState type="error" title="Không thể tải danh sách khóa học" :message="error" action-label="Thử lại" @action="loadCourses" />
  </section>
  <section v-else-if="courses.length" class="course-grid">
    <CourseCard v-for="course in courses" :key="course.id" :course="course">
      <RouterLink class="inline-link" :to="{ name: 'course-detail', params: { id: course.id } }">
        Quản lý tài liệu →
      </RouterLink>
    </CourseCard>
  </section>
  <AppState
    v-else
    title="Danh sách khóa học đang trống"
    message="Hãy tạo khóa học để bắt đầu tải lên tài liệu học liệu giảng dạy."
    action-label="Tạo khóa học mới"
    @action="openCreate"
  />
  <AppModal
    v-model="showCreate"
    title="Tạo khóa học mới"
    confirm-label="Tạo khóa học"
    :loading="saving"
    @confirm="createCourse"
  >
    <p class="muted">Thông tin khóa học sẽ được lưu trực tiếp vào cơ sở dữ liệu hệ thống.</p>
    <BaseInput v-model="form.name" label="Tên khóa học" placeholder="Ví dụ: Nhập môn Lớp học đảo ngược" :error="formErrors.name" required />
    <BaseInput v-model="form.code" label="Mã khóa học" placeholder="Ví dụ: FLIP-101" :error="formErrors.code" required />
    <label class="field">
      <span class="field__label">Mô tả khóa học</span>
      <textarea v-model="form.description" maxlength="2000" placeholder="Nhập mô tả ngắn gọn về khóa học cho không gian làm việc..." />
      <span v-if="formErrors.description" class="field__error">{{ formErrors.description }}</span>
    </label>
  </AppModal>
</template>
