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
  loading.value = true; error.value = ''
  try { courses.value = (await courseService.list()).courses } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}

function resetForm() { Object.assign(form, { name: '', code: '', description: '' }); Object.keys(formErrors).forEach((key) => { formErrors[key] = '' }) }
function openCreate() { resetForm(); showCreate.value = true }
function validate() {
  formErrors.name = form.name.trim() ? '' : 'Course name is required.'
  formErrors.code = form.code.trim() ? '' : 'Course code is required.'
  return !formErrors.name && !formErrors.code
}
async function createCourse() {
  if (!validate()) return
  saving.value = true
  try {
    const { course } = await courseService.create({ name: form.name.trim(), code: form.code.trim(), description: form.description.trim() || null })
    showCreate.value = false
    toast.show('Course created successfully.')
    router.push({ name: 'course-detail', params: { id: course.id } })
  } catch (requestError) {
    const errors = requestError.errors || {}
    formErrors.name = errors.name?.[0] || ''
    formErrors.code = errors.code?.[0] || requestError.message
  } finally { saving.value = false }
}
onMounted(loadCourses)
</script>

<template>
  <section class="page-heading"><div><p class="eyebrow">LECTURER WORKSPACE</p><h1>Course management</h1><p>Create and open only the courses you own.</p></div><BaseButton @click="openCreate">Create course</BaseButton></section>
  <section v-if="loading" class="course-grid"><div v-for="index in 3" :key="index" class="course-card skeleton" /></section>
  <section v-else-if="error"><AppState type="error" title="Unable to load courses" :message="error" action-label="Try again" @action="loadCourses" /></section>
  <section v-else-if="courses.length" class="course-grid"><CourseCard v-for="course in courses" :key="course.id" :course="course"><RouterLink class="inline-link" :to="{ name: 'course-detail', params: { id: course.id } }">Manage documents →</RouterLink></CourseCard></section>
  <AppState v-else title="Your course list is empty" message="Create a course to begin uploading approved source material." action-label="Create course" @action="openCreate" />
  <AppModal v-model="showCreate" title="Create a course" confirm-label="Create course" :loading="saving" @confirm="createCourse">
    <p class="muted">Course creation is saved immediately to the Laravel API and MySQL.</p>
    <BaseInput v-model="form.name" label="Course name" placeholder="e.g. Foundations of Flipped Learning" :error="formErrors.name" required />
    <BaseInput v-model="form.code" label="Course code" placeholder="e.g. FLIP-101" :error="formErrors.code" required />
    <label class="field"><span class="field__label">Description</span><textarea v-model="form.description" maxlength="2000" placeholder="Describe the course for your workspace." /><span v-if="formErrors.description" class="field__error">{{ formErrors.description }}</span></label>
  </AppModal>
</template>
