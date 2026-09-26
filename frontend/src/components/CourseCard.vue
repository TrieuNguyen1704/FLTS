<script setup>
defineProps({ course: { type: Object, required: true }, compact: Boolean })
</script>

<template>
  <article class="course-card" :class="{ 'course-card--compact': compact }">
    <div class="course-card__code">{{ course.code }}</div>
    <h3>{{ course.name }}</h3>
    <p>{{ course.description || 'No course description has been provided yet.' }}</p>
    <footer><span v-if="course.lecturer">Led by {{ course.lecturer.name }}</span><slot /></footer>
  </article>
</template>
