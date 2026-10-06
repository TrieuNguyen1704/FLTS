import { computed, reactive } from 'vue'
import { taskService } from '../services/taskService'
import { authStore } from './auth'
import { toast } from './toast'

const state = reactive({
  tasks: [],
  activeCount: 0,
  loading: false,
  retryingId: null,
  isDrawerOpen: false,
})

let pollTimeoutId = null
let isInitialized = false
// Track known status so we notify exactly once when a task transitions to completed or failed
const notifiedTaskStatuses = new Map()

async function fetchTasks() {
  const currentUser = authStore.user.value
  if (!currentUser || currentUser.role !== 'lecturer') {
    state.tasks = []
    state.activeCount = 0
    return
  }

  try {
    const data = await taskService.getTasks()
    const incomingTasks = data.tasks || []
    state.activeCount = data.active_count || 0

    if (!isInitialized) {
      // First run: register current states without spamming notifications
      for (const t of incomingTasks) {
        if (!t.is_active) {
          notifiedTaskStatuses.set(t.id, t.status)
        }
      }
      isInitialized = true
    } else {
      // Subsequent runs: detect completion or failure
      for (const t of incomingTasks) {
        const prevStatus = notifiedTaskStatuses.get(t.id)
        if (t.status === 'completed' && prevStatus !== 'completed') {
          notifiedTaskStatuses.set(t.id, 'completed')
          toast.show(`Tác vụ "${t.title}" đã hoàn tất thành công.`, 'success')
        } else if (t.status === 'failed' && prevStatus !== 'failed') {
          notifiedTaskStatuses.set(t.id, 'failed')
          const msg = t.error_message ? `: ${t.error_message}` : ''
          toast.show(`Tác vụ "${t.title}" xử lý thất bại${msg}`, 'error')
        }
      }
    }

    state.tasks = incomingTasks
  } catch (err) {
    // Silent fail during background polling to prevent disruption
    console.warn('Background task polling warning:', err)
  }
}

function scheduleNextPoll() {
  clearTimeout(pollTimeoutId)
  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
    return // paused while tab is hidden
  }
  const currentUser = authStore.user.value
  if (!currentUser || currentUser.role !== 'lecturer') {
    return
  }

  // 3.5s if there are active jobs in flight, 15s when idle
  const delay = state.activeCount > 0 ? 3500 : 15000
  pollTimeoutId = setTimeout(async () => {
    await fetchTasks()
    scheduleNextPoll()
  }, delay)
}

function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    // Immediate refresh on return
    fetchTasks().then(() => scheduleNextPoll())
  } else {
    clearTimeout(pollTimeoutId)
  }
}

let listenerAttached = false

function startPolling() {
  if (!listenerAttached && typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', handleVisibilityChange)
    listenerAttached = true
  }
  fetchTasks().then(() => scheduleNextPoll())
}

function stopPolling() {
  clearTimeout(pollTimeoutId)
  if (listenerAttached && typeof document !== 'undefined') {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    listenerAttached = false
  }
}

async function retryTask(task) {
  state.retryingId = task.id
  try {
    if (task.type === 'document_processing') {
      await taskService.retryDocumentTask(task.course_id, task.resource_id)
    } else if (task.type === 'quiz_generation') {
      await taskService.retryQuizTask(task.course_id, task.resource_id)
    }
    toast.show(`Đã gửi lại yêu cầu xử lý cho "${task.title}".`, 'success')
    // Remove from notified map so retry completion can trigger toast
    notifiedTaskStatuses.delete(task.id)
    await fetchTasks()
    scheduleNextPoll()
  } catch (err) {
    toast.show(err.message || 'Không thể thử lại tác vụ.', 'error')
  } finally {
    state.retryingId = null
  }
}

function toggleDrawer() {
  state.isDrawerOpen = !state.isDrawerOpen
}

function closeDrawer() {
  state.isDrawerOpen = false
}

export const backgroundTasks = {
  state,
  hasActiveTasks: computed(() => state.activeCount > 0),
  fetchTasks,
  startPolling,
  stopPolling,
  retryTask,
  toggleDrawer,
  closeDrawer,
}
