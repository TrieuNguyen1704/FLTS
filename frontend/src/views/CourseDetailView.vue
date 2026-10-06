<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import FileDropzone from '../components/FileDropzone.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { courseService } from '../services/courseService'
import { documentService } from '../services/documentService'
import { learningObjectService } from '../services/learningObjectService'
import { ragService } from '../services/ragService'
import { backgroundTasks } from '../stores/backgroundTasks'
import { toast } from '../stores/toast'
import { formatDate, formatFileSize } from '../utils/formatters'

const route = useRoute()
const router = useRouter()

// Data state
const course = ref(null)
const documents = ref([])
const quizzes = ref([])
const students = ref([])
const availableStudents = ref([])

// Tab state
const validTabs = ['overview', 'documents', 'quizzes', 'students']
const activeTab = ref(validTabs.includes(route.query.tab) ? route.query.tab : 'overview')

function setTab(tab) {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
}

watch(
  () => route.query.tab,
  (newTab) => {
    if (newTab && validTabs.includes(newTab) && newTab !== activeTab.value) {
      activeTab.value = newTab
    }
  }
)

// UI & Form states
const loading = ref(true)
const error = ref('')
const uploading = ref(false)
const documentToDelete = ref(null)
const deleting = ref(false)
const showEdit = ref(false)
const savingCourse = ref(false)
const searchQuery = ref('')
const editForm = reactive({ name: '', code: '', description: '' })
const editErrors = reactive({ name: '', code: '' })
const processingIds = ref({})

// Student enrollment state
const studentSearchQuery = ref('')
const showEnrollModal = ref(false)
const searchingStudents = ref(false)
const enrollingId = ref(null)
const studentToUnenroll = ref(null)
const unenrolling = ref(false)
const studentFilter = ref('')

async function loadCourse() {
  loading.value = true
  error.value = ''
  try {
    const courseId = route.params.id
    const [courseResult, documentResult, quizResult, studentResult] = await Promise.all([
      courseService.get(courseId),
      documentService.list(courseId, searchQuery.value.trim()),
      learningObjectService.list(courseId),
      courseService.getStudents(courseId),
    ])
    course.value = courseResult.course
    documents.value = documentResult.documents
    quizzes.value = quizResult.learning_objects || []
    students.value = studentResult.students || []
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải thông tin khóa học.'
  } finally {
    loading.value = false
  }
}

async function loadDocumentsOnly() {
  try {
    const documentResult = await documentService.list(course.value.id, searchQuery.value.trim())
    documents.value = documentResult.documents
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tải danh sách tài liệu.', 'error')
  }
}

async function loadStudentsOnly() {
  try {
    const studentResult = await courseService.getStudents(course.value.id)
    students.value = studentResult.students || []
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tải danh sách sinh viên.', 'error')
  }
}

function openEdit() {
  Object.assign(editForm, {
    name: course.value.name,
    code: course.value.code,
    description: course.value.description || '',
  })
  editErrors.name = ''
  editErrors.code = ''
  showEdit.value = true
}

async function saveCourse() {
  editErrors.name = editForm.name.trim() ? '' : 'Vui lòng nhập tên khóa học.'
  editErrors.code = editForm.code.trim() ? '' : 'Vui lòng nhập mã khóa học.'
  if (editErrors.name || editErrors.code) return
  savingCourse.value = true
  try {
    course.value = (
      await courseService.update(course.value.id, {
        name: editForm.name.trim(),
        code: editForm.code.trim(),
        description: editForm.description.trim() || null,
      })
    ).course
    showEdit.value = false
    toast.show('Thông tin khóa học đã được cập nhật thành công.')
  } catch (requestError) {
    const errors = requestError.errors || {}
    editErrors.name = errors.name?.[0] || ''
    editErrors.code = errors.code?.[0] || requestError.message
  } finally {
    savingCourse.value = false
  }
}

async function upload(file) {
  uploading.value = true
  try {
    await documentService.upload(course.value.id, file)
    await loadDocumentsOnly()
    toast.show('Tải lên tài liệu thành công. Trạng thái: Chờ xử lý.')
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tải lên tài liệu.', 'error')
  } finally {
    uploading.value = false
  }
}

async function removeDocument() {
  if (!documentToDelete.value) return
  deleting.value = true
  try {
    await documentService.remove(course.value.id, documentToDelete.value.id)
    documents.value = documents.value.filter((d) => d.id !== documentToDelete.value.id)
    toast.show('Đã xóa tài liệu.')
    documentToDelete.value = null
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể xóa tài liệu.', 'error')
  } finally {
    deleting.value = false
  }
}

async function downloadDocument(doc) {
  try {
    await documentService.download(course.value.id, doc)
    toast.show('Đang bắt đầu tải xuống...')
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể tải xuống tài liệu.', 'error')
  }
}

async function processDocument(doc) {
  processingIds.value = { ...processingIds.value, [doc.id]: true }
  try {
    const request =
      doc.processing_status === 'failed'
        ? ragService.retryProcessing(course.value.id, doc.id)
        : ragService.startProcessing(course.value.id, doc.id)
    await request
    toast.show('Tài liệu đã được đưa vào hàng đợi xử lý.')
    doc.processing_status = 'processing'
    // Refresh background tasks store
    backgroundTasks.fetchTasks()
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể bắt đầu xử lý tài liệu.', 'error')
  } finally {
    const next = { ...processingIds.value }
    delete next[doc.id]
    processingIds.value = next
  }
}

// Student management functions
async function openEnrollModal() {
  studentSearchQuery.value = ''
  availableStudents.value = []
  showEnrollModal.value = true
  await searchAvailableStudents()
}

async function searchAvailableStudents() {
  searchingStudents.value = true
  try {
    const res = await courseService.getAvailableStudents(
      course.value.id,
      studentSearchQuery.value.trim()
    )
    availableStudents.value = res.students || []
  } catch (err) {
    toast.show(err.message || 'Không thể tìm kiếm sinh viên.', 'error')
  } finally {
    searchingStudents.value = false
  }
}

async function enrollStudent(student) {
  enrollingId.value = student.id
  try {
    await courseService.enrollStudent(course.value.id, student.id)
    toast.show(`Đã ghi danh sinh viên ${student.name} vào khóa học.`)
    availableStudents.value = availableStudents.value.filter((s) => s.id !== student.id)
    await loadStudentsOnly()
  } catch (err) {
    toast.show(err.message || 'Không thể ghi danh sinh viên.', 'error')
  } finally {
    enrollingId.value = null
  }
}

async function confirmUnenroll() {
  if (!studentToUnenroll.value) return
  unenrolling.value = true
  try {
    await courseService.unenrollStudent(course.value.id, studentToUnenroll.value.id)
    toast.show(`Đã hủy ghi danh sinh viên ${studentToUnenroll.value.name}.`)
    students.value = students.value.filter((s) => s.id !== studentToUnenroll.value.id)
    studentToUnenroll.value = null
  } catch (err) {
    toast.show(err.message || 'Không thể hủy ghi danh sinh viên.', 'error')
  } finally {
    unenrolling.value = false
  }
}

const filteredStudents = computed(() => {
  const query = studentFilter.value.trim().toLowerCase()
  if (!query) return students.value
  return students.value.filter(
    (s) =>
      s.name.toLowerCase().includes(query) ||
      s.email.toLowerCase().includes(query)
  )
})

onMounted(loadCourse)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'course-management' })">
    ← Quay lại danh sách khóa học
  </button>

  <AppState
    v-if="loading"
    type="loading"
    title="Đang tải dữ liệu khóa học"
    message="Đang nạp thông tin tổng quan, tài liệu, bài kiểm tra và sinh viên..."
  />
  <AppState
    v-else-if="error"
    type="error"
    title="Không thể mở khóa học này"
    :message="error"
    action-label="Quay lại danh sách khóa học"
    @action="router.push({ name: 'course-management' })"
  />

  <template v-else>
    <!-- Course Hero Section -->
    <section class="course-hero">
      <div class="course-hero__info">
        <p class="eyebrow">{{ course.code }}</p>
        <h1>{{ course.name }}</h1>
        <p class="course-hero__desc">
          {{ course.description || 'Chưa có mô tả chi tiết cho khóa học này.' }}
        </p>
      </div>
      <div class="course-hero__meta">
        <div class="meta-item">
          <span>Giảng viên phụ trách</span>
          <strong>{{ course.lecturer?.name || 'Bạn' }}</strong>
        </div>
        <div class="course-hero__buttons">
          <BaseButton variant="secondary" @click="openEdit">Chỉnh sửa</BaseButton>
          <RouterLink
            class="button"
            :to="{ name: 'learning-objects', params: { courseId: course.id } }"
          >
            Quản lý Quiz
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- Navigation Tabs -->
    <nav class="course-tabs" aria-label="Các phần của khóa học">
      <button
        class="course-tab"
        :class="{ 'course-tab--active': activeTab === 'overview' }"
        @click="setTab('overview')"
      >
        Tổng quan
      </button>
      <button
        class="course-tab"
        :class="{ 'course-tab--active': activeTab === 'documents' }"
        @click="setTab('documents')"
      >
        Tài liệu
        <span class="tab-badge">{{ documents.length }}</span>
      </button>
      <button
        class="course-tab"
        :class="{ 'course-tab--active': activeTab === 'quizzes' }"
        @click="setTab('quizzes')"
      >
        Quiz trắc nghiệm
        <span class="tab-badge">{{ quizzes.length }}</span>
      </button>
      <button
        class="course-tab"
        :class="{ 'course-tab--active': activeTab === 'students' }"
        @click="setTab('students')"
      >
        Sinh viên
        <span class="tab-badge">{{ students.length }}</span>
      </button>
    </nav>

    <!-- TAB 1: TỔNG QUAN -->
    <section v-if="activeTab === 'overview'" class="tab-pane">
      <div class="summary-grid">
        <article class="summary-card" @click="setTab('documents')" style="cursor: pointer;">
          <span class="summary-card__label">Tài liệu học tập</span>
          <strong>{{ documents.length }}</strong>
          <small>Tệp PDF/DOC/DOCX đã tải lên</small>
        </article>
        <article class="summary-card" @click="setTab('quizzes')" style="cursor: pointer;">
          <span class="summary-card__label">Bài kiểm tra Quiz</span>
          <strong>{{ quizzes.length }}</strong>
          <small>Bộ câu hỏi trắc nghiệm</small>
        </article>
        <article class="summary-card" @click="setTab('students')" style="cursor: pointer;">
          <span class="summary-card__label">Sinh viên ghi danh</span>
          <strong>{{ students.length }}</strong>
          <small>Tài khoản đang tham gia</small>
        </article>
      </div>

      <div class="overview-details">
        <div class="overview-box">
          <h3>Thông tin học phần</h3>
          <dl class="info-list">
            <div class="info-row">
              <dt>Mã học phần:</dt>
              <dd><strong>{{ course.code }}</strong></dd>
            </div>
            <div class="info-row">
              <dt>Tên học phần:</dt>
              <dd>{{ course.name }}</dd>
            </div>
            <div class="info-row">
              <dt>Giảng viên:</dt>
              <dd>{{ course.lecturer?.name }} ({{ course.lecturer?.email }})</dd>
            </div>
            <div class="info-row">
              <dt>Mô tả:</dt>
              <dd>{{ course.description || 'Chưa cập nhật' }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <!-- TAB 2: TÀI LIỆU -->
    <section v-else-if="activeTab === 'documents'" class="tab-pane">
      <header class="section-header">
        <div>
          <h2>Tài liệu giảng dạy</h2>
          <p>Hỗ trợ định dạng PDF, DOC hoặc DOCX dung lượng tối đa 10 MB.</p>
        </div>
        <span class="count-chip">{{ documents.length }} tài liệu</span>
      </header>

      <FileDropzone v-if="!uploading" @selected="upload" />
      <AppState
        v-else
        type="loading"
        title="Đang tải lên tài liệu"
        message="Tệp tin đang được xác thực và lưu trữ vào kho tài liệu."
      />

      <form class="toolbar" @submit.prevent="loadDocumentsOnly">
        <input
          v-model="searchQuery"
          placeholder="Tìm kiếm tài liệu theo tên..."
          aria-label="Tìm kiếm tài liệu"
        />
        <BaseButton type="submit" variant="secondary">Tìm kiếm</BaseButton>
      </form>

      <div v-if="documents.length" class="document-table-wrap">
        <table class="document-table">
          <thead>
            <tr>
              <th>Tài liệu</th>
              <th>Định dạng</th>
              <th>Dung lượng</th>
              <th>Thời gian tải</th>
              <th>Trạng thái</th>
              <th aria-label="Thao tác" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in documents" :key="doc.id">
              <td>
                <strong>{{ doc.original_name }}</strong>
                <small>{{ doc.mime_type }}</small>
              </td>
              <td>{{ doc.extension.toUpperCase() }}</td>
              <td>{{ formatFileSize(doc.size_bytes) }}</td>
              <td>{{ formatDate(doc.created_at) }}</td>
              <td><StatusBadge :status="doc.processing_status" /></td>
              <td class="table-actions">
                <BaseButton variant="secondary" @click="downloadDocument(doc)">Tải về</BaseButton>
                <BaseButton
                  v-if="doc.processing_status !== 'processing'"
                  variant="secondary"
                  :loading="Boolean(processingIds[doc.id])"
                  @click="processDocument(doc)"
                >
                  {{ doc.processing_status === 'failed' ? 'Thử xử lý lại' : 'Xử lý tài liệu' }}
                </BaseButton>
                <BaseButton variant="danger-ghost" @click="documentToDelete = doc">Xóa</BaseButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppState
        v-else
        title="Chưa có tài liệu nào"
        message="Tải lên tài liệu giảng dạy đầu tiên để xây dựng ngân hàng tri thức cho môn học."
      />
    </section>

    <!-- TAB 3: QUIZ TRẮC NGHIỆM -->
    <section v-else-if="activeTab === 'quizzes'" class="tab-pane">
      <header class="section-header">
        <div>
          <h2>Bộ câu hỏi trắc nghiệm (Quiz)</h2>
          <p>Tạo và quản lý các bài kiểm tra dựa trên nội dung tài liệu giảng dạy đã xử lý.</p>
        </div>
        <RouterLink
          class="button"
          :to="{ name: 'learning-objects', params: { courseId: course.id } }"
        >
          + Tạo Quiz mới
        </RouterLink>
      </header>

      <div v-if="quizzes.length" class="learning-object-grid">
        <article v-for="quiz in quizzes" :key="quiz.id" class="learning-card">
          <header class="learning-card__header">
            <span class="learning-card__type">Quiz</span>
            <span :class="`status-chip status-chip--${quiz.status}`">
              {{ quiz.status === 'published' ? 'Đã xuất bản' : quiz.status === 'archived' ? 'Đã lưu trữ' : 'Bản nháp' }}
            </span>
          </header>
          <h3>{{ quiz.title }}</h3>
          <p>{{ quiz.description || 'Bài kiểm tra kiến thức môn học.' }}</p>
          <div class="learning-card__footer">
            <span v-if="quiz.quiz" class="quiz-spec">
              {{ quiz.quiz.total_questions || '—' }} câu hỏi | Đạt: {{ quiz.quiz.passing_score }}%
            </span>
            <RouterLink
              class="button button--secondary button--small"
              :to="{ name: 'quiz-editor', params: { courseId: course.id, objectId: quiz.id } }"
            >
              Chỉnh sửa & Xem
            </RouterLink>
          </div>
        </article>
      </div>

      <AppState
        v-else
        title="Chưa có Quiz nào"
        message="Khóa học chưa có bài kiểm tra trắc nghiệm nào. Hãy tạo Quiz đầu tiên từ các tài liệu đã xử lý."
        action-label="Tạo Quiz ngay"
        @action="router.push({ name: 'learning-objects', params: { courseId: course.id } })"
      />
    </section>

    <!-- TAB 4: SINH VIÊN -->
    <section v-else-if="activeTab === 'students'" class="tab-pane">
      <header class="section-header">
        <div>
          <h2>Quản lý sinh viên ghi danh</h2>
          <p>Danh sách sinh viên có quyền truy cập khóa học và làm bài kiểm tra trắc nghiệm.</p>
        </div>
        <BaseButton @click="openEnrollModal">+ Ghi danh sinh viên</BaseButton>
      </header>

      <div class="toolbar">
        <input
          v-model="studentFilter"
          placeholder="Lọc sinh viên theo tên hoặc email..."
          aria-label="Lọc sinh viên"
        />
        <span class="count-chip">{{ filteredStudents.length }} sinh viên</span>
      </div>

      <div v-if="filteredStudents.length" class="document-table-wrap">
        <table class="document-table">
          <thead>
            <tr>
              <th>Họ và tên</th>
              <th>Email</th>
              <th>Trạng thái</th>
              <th>Ngày ghi danh</th>
              <th>Số lần làm Quiz</th>
              <th aria-label="Thao tác" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="student in filteredStudents" :key="student.id">
              <td>
                <strong>{{ student.name }}</strong>
              </td>
              <td>{{ student.email }}</td>
              <td>
                <span class="status-chip status-chip--active">Hoạt động</span>
              </td>
              <td>{{ formatDate(student.enrolled_at) }}</td>
              <td>
                <span class="attempt-count">{{ student.attempts_count || 0 }} lượt</span>
              </td>
              <td class="table-actions">
                <BaseButton
                  variant="danger-ghost"
                  @click="studentToUnenroll = student"
                >
                  Hủy ghi danh
                </BaseButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppState
        v-else
        title="Chưa có sinh viên nào ghi danh"
        message="Hãy ghi danh sinh viên vào khóa học để họ có thể xem tài liệu và làm các bài kiểm tra."
        action-label="Ghi danh sinh viên"
        @action="openEnrollModal"
      />
    </section>
  </template>

  <!-- MODAL: XÓA TÀI LIỆU -->
  <AppModal
    v-model="documentToDelete"
    title="Xác nhận xóa tài liệu"
    confirm-label="Xóa tài liệu"
    :danger="true"
    :loading="deleting"
    @confirm="removeDocument"
  >
    <p>
      Bạn có chắc chắn muốn xóa tài liệu <strong>{{ documentToDelete?.original_name }}</strong>? Thao tác này sẽ xóa tệp tin và toàn bộ dữ liệu vector liên quan.
    </p>
  </AppModal>

  <!-- MODAL: CHỈNH SỬA KHÓA HỌC -->
  <AppModal
    v-model="showEdit"
    title="Chỉnh sửa thông tin khóa học"
    confirm-label="Lưu thay đổi"
    :loading="savingCourse"
    @confirm="saveCourse"
  >
    <BaseInput v-model="editForm.name" label="Tên khóa học" :error="editErrors.name" required />
    <BaseInput v-model="editForm.code" label="Mã khóa học" :error="editErrors.code" required />
    <label class="field">
      <span class="field__label">Mô tả khóa học</span>
      <textarea
        v-model="editForm.description"
        maxlength="2000"
        placeholder="Nhập mô tả cho khóa học..."
      />
    </label>
  </AppModal>

  <!-- MODAL: GHI DANH SINH VIÊN -->
  <AppModal
    v-model="showEnrollModal"
    title="Ghi danh sinh viên mới"
    :hide-confirm="true"
    cancel-label="Đóng"
  >
    <div class="enroll-modal">
      <p class="enroll-modal__desc">
        Tìm kiếm sinh viên có tài khoản đang hoạt động để cấp quyền truy cập khóa học.
      </p>

      <form class="enroll-search-form" @submit.prevent="searchAvailableStudents">
        <input
          v-model="studentSearchQuery"
          placeholder="Nhập tên hoặc email sinh viên..."
          aria-label="Tìm kiếm sinh viên khả dụng"
        />
        <BaseButton type="submit" variant="secondary" :loading="searchingStudents">
          Tìm
        </BaseButton>
      </form>

      <div class="enroll-results">
        <div v-if="searchingStudents" class="enroll-loading">
          Đang tìm kiếm sinh viên khả dụng...
        </div>
        <div
          v-else-if="availableStudents.length === 0"
          class="enroll-empty"
        >
          Không tìm thấy sinh viên nào khả dụng.
        </div>
        <ul v-else class="enroll-list">
          <li
            v-for="student in availableStudents"
            :key="student.id"
            class="enroll-item"
          >
            <div class="enroll-item__info">
              <strong>{{ student.name }}</strong>
              <small>{{ student.email }}</small>
            </div>
            <BaseButton
              variant="secondary"
              :loading="enrollingId === student.id"
              @click="enrollStudent(student)"
            >
              Ghi danh
            </BaseButton>
          </li>
        </ul>
      </div>
    </div>
  </AppModal>

  <!-- MODAL: HỦY GHI DANH SINH VIÊN -->
  <AppModal
    v-model="studentToUnenroll"
    title="Xác nhận hủy ghi danh"
    confirm-label="Hủy ghi danh"
    :danger="true"
    :loading="unenrolling"
    @confirm="confirmUnenroll"
  >
    <p>
      Bạn có chắc chắn muốn hủy quyền truy cập của sinh viên <strong>{{ studentToUnenroll?.name }}</strong> ({{ studentToUnenroll?.email }})?
    </p>
    <p class="modal-notice">
      Lưu ý: Toàn bộ lịch sử làm bài kiểm tra và điểm số của sinh viên này trong khóa học vẫn được bảo lưu an toàn trong hệ thống.
    </p>
  </AppModal>
</template>

<style scoped>
.back-link {
  margin-bottom: 16px;
  border: 0;
  padding: 0;
  background: none;
  color: #2958d8;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
}

.back-link:hover {
  text-decoration: underline;
}

.course-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  border-bottom: 1px solid #e1e7f2;
  padding-bottom: 24px;
  margin-bottom: 20px;
}

.course-hero__info {
  flex: 1;
}

.course-hero__desc {
  max-width: 680px;
  color: #53607b;
  font-size: 0.92rem;
  line-height: 1.6;
}

.course-hero__meta {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 220px;
  align-items: flex-end;
}

.meta-item {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  font-size: 0.8rem;
  color: #64748b;
}

.meta-item strong {
  font-size: 0.95rem;
  color: #17275a;
}

.course-hero__buttons {
  display: flex;
  gap: 8px;
}

/* Tab Navigation */
.course-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e2e8f5;
  margin-bottom: 28px;
}

.course-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.course-tab:hover {
  color: #1e293b;
  background: rgba(241, 245, 249, 0.5);
}

.course-tab--active {
  color: #2958d8;
  border-bottom-color: #2958d8;
  font-weight: 700;
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: #e2e8f5;
  color: #475569;
  font-size: 0.72rem;
  font-weight: 700;
}

.course-tab--active .tab-badge {
  background: #e0e7ff;
  color: #2958d8;
}

.tab-pane {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Overview section */
.overview-box {
  background: #fff;
  border: 1px solid #e2e8f5;
  border-radius: 6px;
  padding: 20px 24px;
}

.overview-box h3 {
  margin-top: 0;
  margin-bottom: 16px;
  color: #17275a;
  font-size: 1.05rem;
}

.info-list {
  display: grid;
  gap: 12px;
  margin: 0;
}

.info-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  font-size: 0.88rem;
}

.info-row dt {
  color: #64748b;
  font-weight: 600;
}

.info-row dd {
  margin: 0;
  color: #1e293b;
}

/* Quizzes cards */
.learning-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}

.quiz-spec {
  font-size: 0.78rem;
  color: #64748b;
}

.attempt-count {
  font-weight: 600;
  color: #1e293b;
}

/* Enrollment Modal */
.enroll-modal {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.enroll-modal__desc {
  color: #64748b;
  font-size: 0.86rem;
  margin: 0;
}

.enroll-search-form {
  display: flex;
  gap: 8px;
}

.enroll-search-form input {
  flex: 1;
}

.enroll-results {
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid #e2e8f5;
  border-radius: 6px;
  padding: 8px;
  background: #f8fafc;
}

.enroll-loading,
.enroll-empty {
  padding: 24px;
  text-align: center;
  color: #64748b;
  font-size: 0.84rem;
}

.enroll-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.enroll-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #fff;
  border: 1px solid #e2e8f5;
  border-radius: 4px;
}

.enroll-item__info {
  display: flex;
  flex-direction: column;
}

.enroll-item__info strong {
  font-size: 0.88rem;
  color: #1e293b;
}

.enroll-item__info small {
  font-size: 0.76rem;
  color: #64748b;
}

.modal-notice {
  font-size: 0.82rem;
  color: #64748b;
  background: #f8fafc;
  padding: 8px 12px;
  border-radius: 4px;
  border-left: 3px solid #2958d8;
  margin-top: 8px;
}

@media (max-width: 768px) {
  .course-hero {
    flex-direction: column;
  }
  .course-hero__meta {
    align-items: flex-start;
  }
  .meta-item {
    align-items: flex-start;
  }
  .course-tabs {
    overflow-x: auto;
  }
  .info-row {
    grid-template-columns: 1fr;
    gap: 4px;
  }
}
</style>
