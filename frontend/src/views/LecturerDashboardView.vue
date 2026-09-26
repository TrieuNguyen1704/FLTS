<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import CourseCard from '../components/CourseCard.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'

const router = useRouter()
const courses = ref([])
const documentCount = ref(0)
const loading = ref(true)
const error = ref('')
const recentCourses = computed(() => courses.value.slice(0, 3))

async function loadDashboard() {
  loading.value = true; error.value = ''
  try {
    courses.value = (await courseService.list()).courses
    const documents = await Promise.all(courses.value.map((course) => documentService.list(course.id)))
    documentCount.value = documents.reduce((total, result) => total + result.documents.length, 0)
  } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}

onMounted(loadDashboard)
</script>

<template>
  <section class="page-heading">
    <div><p class="eyebrow">LECTURER WORKSPACE</p><h1>Good to see you.</h1><p>Keep your courses and source documents organised for the next learning workflow.</p></div>
    <BaseButton @click="router.push({ name: 'course-management' })">Create course</BaseButton>
  </section>
  <section class="notice-banner"><strong>Sprint 1 scope</strong><span>Uploads are safely stored with metadata and remain <b>Pending processing</b>. No extraction, embedding, vector storage, or RAG is running.</span></section>
  <section v-if="loading" class="summary-grid"><div v-for="index in 3" :key="index" class="summary-card skeleton" /></section>
  <template v-else-if="!error">
    <section class="summary-grid">
      <article class="summary-card"><span class="summary-card__label">My courses</span><strong>{{ courses.length }}</strong><small>Courses you own</small></article>
      <article class="summary-card"><span class="summary-card__label">Document uploads</span><strong>{{ documentCount }}</strong><small>Stored teaching documents</small></article>
      <article class="summary-card"><span class="summary-card__label">Processing</span><strong>0</strong><small>No processing worker in Sprint 1</small></article>
    </section>
    <section class="content-section"><header class="section-header"><div><h2>Recent courses</h2><p>Open a course to manage its source documents.</p></div><RouterLink :to="{ name: 'course-management' }">View all courses</RouterLink></header>
      <div v-if="recentCourses.length" class="course-grid"><CourseCard v-for="course in recentCourses" :key="course.id" :course="course"><RouterLink class="inline-link" :to="{ name: 'course-detail', params: { id: course.id } }">Open course →</RouterLink></CourseCard></div>
      <AppState v-else title="No courses yet" message="Create your first course to start storing teaching documents." action-label="Create course" @action="router.push({ name: 'course-management' })" />
    </section>
  </template>
  <AppState v-else type="error" title="Unable to load the dashboard" :message="error" action-label="Try again" @action="loadDashboard" />
</template>
