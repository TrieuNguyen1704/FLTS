<script setup>
defineProps({
  course: { type: Object, required: true },
  compact: Boolean,
})
</script>

<template>
  <article class="course-card" :class="{ 'course-card--compact': compact }">
    <div class="course-card__header-bar">
      <span class="course-card__code">{{ course.code }}</span>
    </div>

    <div class="course-card__body">
      <h3 class="course-card__title" :title="course.name">
        {{ course.name }}
      </h3>

      <p class="course-card__desc">
        {{ course.description || 'Chưa có mô tả cho khóa học này.' }}
      </p>

      <div v-if="course.lecturer" class="course-card__instructor">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
        <span>{{ course.lecturer.name }}</span>
      </div>
    </div>

    <footer class="course-card__footer">
      <slot />
    </footer>
  </article>
</template>

<style scoped>
.course-card {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--cds-border);
  border-radius: var(--cds-radius-md);
  background: var(--cds-bg-surface);
  box-shadow: var(--cds-shadow-sm);
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  overflow: hidden;
  position: relative;
  min-height: 230px;
}

.course-card:hover {
  transform: translateY(-3px);
  border-color: #b4d5fe;
  box-shadow: var(--cds-shadow-card-hover);
}

.course-card__header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 18px;
  background: linear-gradient(135deg, #002d72 0%, #0056d2 100%);
}

.course-card__code {
  display: inline-block;
  padding: 3px 9px;
  border-radius: var(--cds-radius-sm);
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  backdrop-filter: blur(4px);
}

.course-card__body {
  padding: 16px 18px 12px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.course-card__title {
  margin: 0 0 8px;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--cds-text-primary);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.course-card__desc {
  margin: 0 0 14px;
  font-size: 0.84rem;
  color: var(--cds-text-secondary);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  flex-grow: 1;
}

.course-card__instructor {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  color: var(--cds-text-muted);
  margin-top: auto;
}

.course-card__footer {
  padding: 12px 18px;
  border-top: 1px solid var(--cds-border-light);
  background: #fafbfc;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
</style>
