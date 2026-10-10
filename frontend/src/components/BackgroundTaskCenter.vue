<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { backgroundTasks } from '../stores/backgroundTasks'
import { authStore } from '../stores/auth'

const router = useRouter()

onMounted(() => {
  if (authStore.user.value?.role === 'lecturer') {
    backgroundTasks.startPolling()
  }
})

onUnmounted(() => {
  backgroundTasks.stopPolling()
})

function formatTime(isoString) {
  if (!isoString) return ''
  try {
    const d = new Date(isoString)
    return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  } catch {
    return isoString
  }
}

function navigateToTask(task) {
  if (task.target_url) {
    backgroundTasks.closeDrawer()
    router.push(task.target_url)
  }
}
</script>

<template>
  <div v-if="authStore.user.value?.role === 'lecturer'" class="task-center">
    <!-- Trigger Button in Topbar -->
    <button
      class="task-center__trigger"
      :class="{ 'task-center__trigger--has-active': backgroundTasks.state.activeCount > 0 }"
      title="Trung tâm tác vụ nền"
      @click="backgroundTasks.toggleDrawer"
    >
      <svg class="task-center__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
      </svg>
      <span class="task-center__label">Tác vụ</span>
      <span
        v-if="backgroundTasks.state.activeCount > 0"
        class="task-center__badge"
      >
        {{ backgroundTasks.state.activeCount }}
      </span>
    </button>

    <!-- Slide-over Drawer / Popover Panel -->
    <div
      v-if="backgroundTasks.state.isDrawerOpen"
      class="task-drawer-overlay"
      @click="backgroundTasks.closeDrawer"
    ></div>

    <aside
      v-if="backgroundTasks.state.isDrawerOpen"
      class="task-drawer"
      aria-label="Danh sách tác vụ nền"
    >
      <header class="task-drawer__header">
        <div class="task-drawer__title-wrap">
          <h3 class="task-drawer__title">Tác vụ nền</h3>
          <span
            v-if="backgroundTasks.state.activeCount > 0"
            class="task-drawer__active-indicator"
          >
            {{ backgroundTasks.state.activeCount }} đang xử lý
          </span>
        </div>
        <div class="task-drawer__actions">
          <button
            class="action-btn"
            title="Làm mới danh sách"
            @click="backgroundTasks.fetchTasks"
          >
            Làm mới
          </button>
          <button
            class="action-btn action-btn--close"
            title="Đóng bảng tác vụ"
            @click="backgroundTasks.closeDrawer"
          >
            ✕
          </button>
        </div>
      </header>

      <div class="task-drawer__content">
        <div v-if="backgroundTasks.state.tasks.length === 0" class="task-drawer__empty">
          <p>Chưa có tác vụ nền nào gần đây.</p>
        </div>

        <ul v-else class="task-list">
          <li
            v-for="task in backgroundTasks.state.tasks"
            :key="task.id"
            class="task-card"
            :class="[`task-card--${task.status}`, { 'task-card--active': task.is_active }]"
          >
            <div class="task-card__top">
              <span class="task-card__type">
                {{ task.type === 'document_processing' ? 'Tài liệu' : 'Quiz' }}
              </span>
              <span class="task-card__time">
                {{ formatTime(task.created_at) }}
              </span>
            </div>

            <div class="task-card__body">
              <div class="task-card__title" :title="task.title">{{ task.title }}</div>
              <div v-if="task.course_name" class="task-card__course">
                {{ task.course_code }}: {{ task.course_name }}
              </div>

              <!-- Indeterminate progress bar for active tasks -->
              <div v-if="task.is_active" class="task-progress">
                <div class="task-progress__indeterminate-bar"></div>
              </div>

              <div class="task-card__stage-row">
                <span class="task-card__stage-label">
                  <span
                    class="task-status-dot"
                    :class="`task-status-dot--${task.status}`"
                  ></span>
                  {{ task.stage_label }}
                </span>
              </div>

              <!-- Error detail safe message -->
              <div v-if="task.status === 'failed' && task.error_message" class="task-card__error">
                {{ task.error_message }}
              </div>
            </div>

            <div class="task-card__actions">
              <button
                v-if="task.status === 'failed' && task.retryable"
                class="button-sub button-sub--retry"
                :disabled="backgroundTasks.state.retryingId === task.id"
                @click="backgroundTasks.retryTask(task)"
              >
                {{ backgroundTasks.state.retryingId === task.id ? 'Đang gửi...' : 'Thử lại' }}
              </button>
              <button
                v-if="task.target_url"
                class="button-sub button-sub--link"
                @click="navigateToTask(task)"
              >
                Xem chi tiết
              </button>
            </div>
          </li>
        </ul>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.task-center {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.task-center__trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #dbe2ef;
  border-radius: 4px;
  padding: 6px 11px;
  font-size: 13px;
  font-weight: 600;
  color: #33415e;
  cursor: pointer;
  transition: all 0.15s ease;
}

.task-center__trigger:hover {
  background: var(--cds-blue-tint);
  border-color: #cbd5e1;
}

.task-center__trigger--has-active {
  border-color: var(--cds-blue-primary);
  color: var(--cds-blue-primary);
  background: var(--cds-blue-tint);
}

.task-center__icon {
  flex-shrink: 0;
}

.task-center__trigger--has-active .task-center__icon {
  animation: spin 3s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.task-center__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: var(--cds-blue-primary);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}

/* Slide-over Drawer */
.task-drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(1px);
  z-index: 998;
}

.task-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 380px;
  max-width: 90vw;
  background: #fff;
  border-left: 1px solid #e1e7f2;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.12);
  z-index: 999;
  display: flex;
  flex-direction: column;
}

.task-drawer__header {
  padding: 14px 18px;
  border-bottom: 1px solid #e1e7f2;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.task-drawer__title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.task-drawer__title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #17275a;
}

.task-drawer__active-indicator {
  font-size: 11px;
  background: #eef2ff;
  color: #2958d8;
  padding: 2px 7px;
  border-radius: 10px;
  font-weight: 600;
}

.task-drawer__actions {
  display: flex;
  gap: 6px;
}

.action-btn {
  border: 1px solid #dbe2ef;
  background: #fff;
  color: #4b5874;
  padding: 4px 8px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s;
}

.action-btn:hover {
  background: #f1f5f9;
}

.action-btn--close {
  padding: 4px 7px;
}

.task-drawer__content {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.task-drawer__empty {
  padding: 48px 16px;
  text-align: center;
  color: #66728f;
  font-size: 13px;
}

.task-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.task-card {
  border: 1px solid #e2e8f5;
  border-radius: 6px;
  padding: 12px 14px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: border-color 0.2s ease;
}

.task-card--active {
  border-color: #93b3f5;
  background: #fcfdff;
}

.task-card--failed {
  border-color: #fca5a5;
}

.task-card__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.task-card__type {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #475569;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 3px;
}

.task-card__time {
  font-size: 11px;
  color: #64748b;
}

.task-card__title {
  font-size: 13px;
  font-weight: 650;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.task-card__course {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

/* Indeterminate Progress Bar */
.task-progress {
  height: 4px;
  background: #e0e7ff;
  border-radius: 2px;
  overflow: hidden;
  margin: 6px 0;
}

.task-progress__indeterminate-bar {
  height: 100%;
  width: 40%;
  background: var(--cds-blue-primary);
  border-radius: 2px;
  animation: indeterminate 1.4s infinite ease-in-out;
}

@keyframes indeterminate {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(300%);
  }
}

.task-card__stage-row {
  display: flex;
  align-items: center;
  font-size: 12px;
  color: #33415e;
  margin-top: 2px;
}

.task-card__stage-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.task-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.task-status-dot--pending,
.task-status-dot--queued {
  background: #f59e0b;
}

.task-status-dot--processing,
.task-status-dot--generating {
  background: #2563eb;
  animation: pulse-dot 1.2s infinite ease-in-out;
}

.task-status-dot--completed,
.task-status-dot--processed {
  background: #10b981;
}

.task-status-dot--failed {
  background: #ef4444;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.task-card__error {
  font-size: 12px;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fee2e2;
  border-radius: 4px;
  padding: 6px 8px;
  margin-top: 4px;
  word-break: break-word;
}

.task-card__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 6px;
  border-top: 1px solid #f1f5f9;
}

.button-sub {
  border: 1px solid transparent;
  padding: 4px 9px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s;
}

.button-sub--retry {
  background: #fff;
  border-color: #cbd5e1;
  color: #1e293b;
}

.button-sub--retry:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #94a3b8;
}

.button-sub--link {
  background: transparent;
  color: var(--cds-blue-primary);
}

.button-sub--link:hover {
  text-decoration: underline;
}
</style>
