<script setup>
import { onMounted, ref } from 'vue'
import AppState from '../components/AppState.vue'
import CourseCard from '../components/CourseCard.vue'
import { courseService } from '../services/courseService'

const courses = ref([])
const loading = ref(true)
const error = ref('')
async function loadCourses() {
  loading.value = true; error.value = ''
  try { courses.value = (await courseService.list()).courses } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}
onMounted(loadCourses)
</script>

<template>
  <section class="page-heading"><div><p class="eyebrow">STUDENT WORKSPACE</p><h1>My courses</h1><p>Only courses granted to your account are shown here.</p></div></section>
  <section class="notice-banner"><strong>Access note</strong><span>Your enrolled courses are loaded from the backend. Teaching documents and draft learning content are not available to students in this Sprint 1 demo.</span></section>
  <section v-if="loading" class="course-grid"><div v-for="index in 2" :key="index" class="course-card skeleton" /></section>
  <section v-else-if="error"><AppState type="error" title="Unable to load your courses" :message="error" action-label="Try again" @action="loadCourses" /></section>
  <section v-else-if="courses.length" class="course-grid"><CourseCard v-for="course in courses" :key="course.id" :course="course" compact><span class="course-card__access">Access granted</span></CourseCard></section>
  <AppState v-else title="No course access yet" message="Your lecturer has not granted access to a course for this account." />
</template>
