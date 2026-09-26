<script setup>
defineProps({ course: { type: Object, required: true }, compact: Boolean })
</script>

<template>
  <article class="course-card" :class="{ 'course-card--compact': compact }">
    <div class="course-card__code">{{ course.code }}</div>
    <h3>{{ course.name }}</h3>
    <p>{{ course.description || 'Chưa có mô tả cho khóa học này.' }}</p>
    <footer>
      <span v-if="course.lecturer">Giảng viên: {{ course.lecturer.name }}</span>
      <slot />
    </footer>
  </article>
</template>
