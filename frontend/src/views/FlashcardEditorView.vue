<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import FlashcardPlayer from '../components/FlashcardPlayer.vue'
import MathText from '../components/MathText.vue'
import { learningObjectService } from '../services/learningObjectService'
import { toast } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const object = ref(null)
const loading = ref(true)
const saving = ref(false)
const publishing = ref(false)
const error = ref('')
const preview = ref(false)
const form = reactive({ title: '', description: '', cards: [] })

function hydrate(value) {
  if (value.type !== 'flashcard') throw new Error('Học liệu này không phải bộ Flashcard.')
  object.value = value
  form.title = value.title
  form.description = value.description || ''
  form.cards = (value.flashcard?.cards || []).map((card) => ({
    front: card.front,
    back: card.back,
    hint: card.hint || '',
    citations: card.citations || [],
  }))
}

async function load() {
  loading.value = true
  error.value = ''
  try { hydrate((await learningObjectService.get(route.params.courseId, route.params.objectId)).learning_object) }
  catch (requestError) { error.value = requestError.message || 'Không thể tải bộ Flashcard.' }
  finally { loading.value = false }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const result = await learningObjectService.update(route.params.courseId, route.params.objectId, {
      title: form.title.trim(),
      description: form.description.trim() || null,
      cards: form.cards,
    })
    hydrate(result.learning_object)
    toast.show('Đã lưu thay đổi thành phiên bản mới.')
  } catch (requestError) { error.value = requestError.message || 'Không thể lưu bộ Flashcard.' }
  finally { saving.value = false }
}

async function publish() {
  if (!window.confirm('Xuất bản bộ Flashcard này cho sinh viên?')) return
  publishing.value = true
  error.value = ''
  try {
    hydrate((await learningObjectService.publish(route.params.courseId, route.params.objectId)).learning_object)
    toast.show('Bộ Flashcard đã được xuất bản.')
  } catch (requestError) { error.value = requestError.message || 'Không thể xuất bản bộ Flashcard.' }
  finally { publishing.value = false }
}

async function archive() {
  if (!window.confirm('Lưu trữ bộ Flashcard này? Sinh viên sẽ không còn nhìn thấy học liệu.')) return
  try {
    hydrate((await learningObjectService.archive(route.params.courseId, route.params.objectId)).learning_object)
    toast.show('Bộ Flashcard đã được lưu trữ.')
  } catch (requestError) { error.value = requestError.message || 'Không thể lưu trữ bộ Flashcard.' }
}

function removeCard(index) {
  if (form.cards.length <= 3) {
    error.value = 'Một bộ Flashcard cần có ít nhất 3 thẻ.'
    return
  }
  form.cards.splice(index, 1)
}

function statusLabel(status) {
  return status === 'draft' ? 'Bản nháp' : status === 'published' ? 'Đã xuất bản' : 'Đã lưu trữ'
}

onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'learning-objects', params: { courseId: route.params.courseId } })">Quay lại thư viện học liệu</button>
  <AppState v-if="loading" type="loading" title="Đang tải Flashcard" message="Đang lấy nội dung và lịch sử phiên bản." />
  <AppState v-else-if="error && !object" type="error" title="Không thể mở Flashcard" :message="error" action-label="Thử lại" @action="load" />

  <template v-else>
    <section class="page-heading editor-heading">
      <div>
        <span class="eyebrow">HỌC LIỆU FLASHCARD</span>
        <h1>{{ object.title }}</h1>
        <p>Kiểm tra nội dung, công thức và nguồn dẫn trước khi xuất bản.</p>
      </div>
      <div class="table-actions">
        <span class="version-badge">Phiên bản {{ object.current_version }}</span>
        <span class="status-chip" :class="`status-chip--${object.status}`">{{ statusLabel(object.status) }}</span>

        <div class="editor-mode-toggle" role="group" aria-label="Chế độ xem">
          <button
            type="button"
            class="mode-toggle-btn"
            :class="{ 'mode-toggle-btn--active': !preview }"
            @click="preview = false"
          >
            Biên tập
          </button>
          <button
            type="button"
            class="mode-toggle-btn"
            :class="{ 'mode-toggle-btn--active': preview }"
            @click="preview = true"
          >
            Xem trước
          </button>
        </div>

        <BaseButton v-if="object.status === 'draft'" :loading="publishing" @click="publish">Phê duyệt và xuất bản</BaseButton>
        <BaseButton v-if="object.status !== 'archived'" variant="secondary" @click="archive">Lưu trữ</BaseButton>
      </div>
    </section>

    <FlashcardPlayer v-if="preview" :cards="form.cards" />

    <form v-else class="flashcard-editor" @submit.prevent="save">
      <section class="learning-panel editor-metadata">
        <BaseInput v-model="form.title" label="Tiêu đề bộ Flashcard" :disabled="object.status !== 'draft'" required />
        <label class="field"><span class="field__label">Mô tả</span><textarea v-model="form.description" :disabled="object.status !== 'draft'" /></label>
      </section>

      <article v-for="(card, index) in form.cards" :key="index" class="flashcard-editor__card">
        <header>
          <div><span class="eyebrow">THẺ {{ index + 1 }}</span><strong>{{ card.citations.length }} nguồn dẫn</strong></div>
          <BaseButton v-if="object.status === 'draft'" type="button" variant="danger-ghost" @click="removeCard(index)">Xóa thẻ</BaseButton>
        </header>
        <div class="flashcard-editor__columns">
          <label class="field"><span class="field__label">Mặt trước</span><textarea v-model="card.front" :disabled="object.status !== 'draft'" required /></label>
          <label class="field"><span class="field__label">Mặt sau</span><textarea v-model="card.back" :disabled="object.status !== 'draft'" required /></label>
        </div>
        <label class="field"><span class="field__label">Gợi ý ghi nhớ</span><input v-model="card.hint" :disabled="object.status !== 'draft'" /></label>
        <div class="flashcard-editor__preview">
          <div><small>Mặt trước hiển thị</small><MathText :text="card.front" /></div>
          <div><small>Mặt sau hiển thị</small><MathText :text="card.back" /></div>
        </div>
        <ul class="citation-list"><li v-for="citation in card.citations" :key="citation.vector_id">{{ citation.document_name || 'Tài liệu nguồn' }} · {{ citation.source_locator || citation.vector_id }}</li></ul>
      </article>

      <p v-if="error" class="form-error">{{ error }}</p>
      <div v-if="object.status === 'draft'" class="editor-footer">
        <BaseButton type="submit" :loading="saving">Lưu thành phiên bản mới</BaseButton>
      </div>
    </form>

    <section class="content-section">
      <header class="section-header"><div><h2>Lịch sử phiên bản</h2><p>Mỗi lần lưu chỉnh sửa tạo một snapshot mới.</p></div></header>
      <div class="version-list"><span v-for="version in object.versions" :key="version.id" class="count-chip">Phiên bản {{ version.version_number }}</span></div>
    </section>
  </template>
</template>

<style scoped>
.editor-heading { align-items: flex-end; }
.flashcard-editor { display: grid; gap: 18px; }
.editor-metadata { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.flashcard-editor__card { padding: 22px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .06); }
.flashcard-editor__card header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.flashcard-editor__card header > div { display: flex; align-items: center; gap: 12px; }
.flashcard-editor__card header strong { color: var(--cds-text-secondary); font-size: .78rem; }
.flashcard-editor__columns, .flashcard-editor__preview { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.flashcard-editor__columns textarea { min-height: 120px; }
.flashcard-editor__preview { margin-top: 14px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
.flashcard-editor__preview small { display: block; margin-bottom: 6px; color: var(--cds-text-secondary); font-weight: 700; }
.editor-footer { display: flex; justify-content: space-between; }
.editor-mode-toggle {
  display: inline-flex;
  padding: 3px;
  background: var(--cds-bg-subtle, #f1f5f9);
  border: 1px solid var(--cds-border, #cbd5e1);
  border-radius: 8px;
}
.mode-toggle-btn {
  padding: 6px 14px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--cds-text-secondary);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}
.mode-toggle-btn--active {
  background: #ffffff;
  color: var(--cds-blue-primary);
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}
.version-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  background: var(--cds-bg-subtle, #f1f5f9);
  border: 1px solid var(--cds-border, #cbd5e1);
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--cds-text-secondary);
}
@media (max-width: 760px) { .editor-metadata, .flashcard-editor__columns, .flashcard-editor__preview { grid-template-columns: 1fr; } }
</style>
