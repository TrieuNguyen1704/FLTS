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
const deleting = ref(false)
const deleteTarget = ref(null)
const deleteConfirmation = ref('')
const deleteError = ref('')
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
      name: form.name.trim(), code: form.code.trim(), description: form.description.trim() || null,
    })
    showCreate.value = false
    toast.show('Đã tạo khóa học.')
    router.push({ name: 'course-detail', params: { id: course.id } })
  } catch (requestError) {
    const errors = requestError.errors || {}
    formErrors.name = errors.name?.[0] || ''
    formErrors.code = errors.code?.[0] || requestError.message
  } finally {
    saving.value = false
  }
}

function openDelete(course) {
  deleteTarget.value = course
  deleteConfirmation.value = ''
  deleteError.value = ''
}

async function deleteCourse() {
  if (deleteConfirmation.value !== deleteTarget.value.code) {
    deleteError.value = `Nhập chính xác mã ${deleteTarget.value.code} để xác nhận.`
    return
  }
  deleting.value = true
  deleteError.value = ''
  try {
    await courseService.destroy(deleteTarget.value.id, deleteConfirmation.value)
    courses.value = courses.value.filter((course) => course.id !== deleteTarget.value.id)
    deleteTarget.value = null
    toast.show('Đã xóa khóa học và dữ liệu liên quan.')
  } catch (requestError) {
    deleteError.value = requestError.message || 'Không thể xóa khóa học.'
  } finally {
    deleting.value = false
  }
}

onMounted(loadCourses)
</script>

<template>
  <section class="page-heading">
    <div>
      <span class="eyebrow">GIẢNG VIÊN · QUẢN LÝ KHÓA HỌC</span>
      <h1>Danh sách khóa học</h1>
      <p>Quản lý các khóa học bạn phụ trách, cập nhật thông tin và điều hướng đến không gian học liệu.</p>
    </div>
    <BaseButton @click="openCreate">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      Tạo khóa học mới
    </BaseButton>
  </section>
  <section v-if="loading" class="course-grid"><div v-for="index in 3" :key="index" class="course-card skeleton" /></section>
  <AppState v-else-if="error" type="error" title="Không thể tải danh sách khóa học" :message="error" action-label="Thử lại" @action="loadCourses" />
  <section v-else-if="courses.length" class="course-grid">
    <CourseCard v-for="course in courses" :key="course.id" :course="course">
      <div class="table-actions">
        <RouterLink class="inline-link" :to="{ name: 'course-detail', params: { id: course.id } }">
          Mở khóa học →
        </RouterLink>
        <button class="link-button link-button--danger" @click="openDelete(course)">Xóa</button>
      </div>
    </CourseCard>
  </section>
  <AppState v-else title="Chưa có khóa học nào" message="Tạo khóa học đầu tiên để bắt đầu lưu trữ học liệu và biên soạn bài kiểm tra." action-label="Tạo khóa học" @action="openCreate" />

  <AppModal v-model="showCreate" title="Tạo khóa học" confirm-label="Tạo khóa học" :loading="saving" @confirm="createCourse">
    <BaseInput v-model="form.name" label="Tên khóa học" placeholder="Nhập tên khóa học" :error="formErrors.name" required />
    <BaseInput v-model="form.code" label="Mã khóa học" placeholder="Ví dụ: FLIP-101" :error="formErrors.code" required />
    <label class="field"><span class="field__label">Mô tả</span><textarea v-model="form.description" maxlength="2000" placeholder="Mô tả ngắn về khóa học" /></label>
  </AppModal>

  <AppModal v-if="deleteTarget" :model-value="true" title="Xóa khóa học" confirm-label="Xóa vĩnh viễn" danger :loading="deleting" @update:model-value="deleteTarget = null" @confirm="deleteCourse">
    <p>Thao tác này xóa tài liệu, Quiz, lịch sử làm bài và quyền truy cập của sinh viên trong khóa học.</p>
    <p>Nhập mã <strong>{{ deleteTarget.code }}</strong> để xác nhận.</p>
    <BaseInput v-model="deleteConfirmation" label="Mã khóa học" :placeholder="deleteTarget.code" :error="deleteError" />
  </AppModal>
</template>
