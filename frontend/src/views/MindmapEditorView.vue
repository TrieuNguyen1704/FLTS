<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import MindmapNodeEditor from '../components/MindmapNodeEditor.vue'
import MindmapViewer from '../components/MindmapViewer.vue'
import { learningObjectService } from '../services/learningObjectService'
import { toast } from '../stores/toast'

const route = useRoute(), router = useRouter()
const object = ref(null), loading = ref(true), saving = ref(false), publishing = ref(false), preview = ref(true), error = ref('')
const form = reactive({ title: '', description: '', root: null })
const clone = (value) => JSON.parse(JSON.stringify(value))
function hydrate(value) {
  if (value.type !== 'mindmap') throw new Error('Học liệu này không phải Mindmap.')
  object.value = value; form.title = value.title; form.description = value.description || ''; form.root = clone(value.mindmap?.root)
}
async function load() { loading.value = true; error.value = ''; try { hydrate((await learningObjectService.get(route.params.courseId, route.params.objectId)).learning_object) } catch (e) { error.value = e.message || 'Không thể tải Mindmap.' } finally { loading.value = false } }
async function save() { saving.value = true; error.value = ''; try { hydrate((await learningObjectService.update(route.params.courseId, route.params.objectId, { title: form.title.trim(), description: form.description.trim() || null, root: form.root })).learning_object); toast.show('Đã lưu Mindmap thành phiên bản mới.') } catch (e) { error.value = e.message || 'Không thể lưu Mindmap.' } finally { saving.value = false } }
async function publish() { if (!window.confirm('Xuất bản Mindmap này cho sinh viên?')) return; publishing.value = true; try { hydrate((await learningObjectService.publish(route.params.courseId, route.params.objectId)).learning_object); toast.show('Mindmap đã được xuất bản.') } catch (e) { error.value = e.message || 'Không thể xuất bản Mindmap.' } finally { publishing.value = false } }
async function archive() { if (!window.confirm('Lưu trữ Mindmap này?')) return; try { hydrate((await learningObjectService.archive(route.params.courseId, route.params.objectId)).learning_object); toast.show('Mindmap đã được lưu trữ.') } catch (e) { error.value = e.message || 'Không thể lưu trữ Mindmap.' } }
const statusLabel = (status) => status === 'draft' ? 'Bản nháp' : status === 'published' ? 'Đã xuất bản' : 'Đã lưu trữ'
onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'learning-objects', params: { courseId: route.params.courseId }, query: { type: 'mindmap' } })">Quay lại thư viện học liệu</button>
  <AppState v-if="loading" type="loading" title="Đang tải Mindmap" message="Đang lấy nội dung và lịch sử phiên bản." />
  <AppState v-else-if="error && !object" type="error" title="Không thể mở Mindmap" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading editor-heading"><div><span class="eyebrow">SƠ ĐỒ TƯ DUY</span><h1>{{ object.title }}</h1><p>Kiểm tra cấu trúc kiến thức, công thức và nguồn dẫn trước khi xuất bản.</p></div><div class="table-actions"><span class="count-chip">Phiên bản {{ object.current_version }}</span><span class="status-chip" :class="`status-chip--${object.status}`">{{ statusLabel(object.status) }}</span><BaseButton v-if="object.status === 'draft'" :loading="publishing" @click="publish">Phê duyệt và xuất bản</BaseButton><BaseButton v-if="object.status !== 'archived'" variant="secondary" @click="archive">Lưu trữ</BaseButton></div></section>
    <div class="editor-mode-toggle"><button :class="{ active: !preview }" @click="preview = false">Biên tập</button><button :class="{ active: preview }" @click="preview = true">Xem sơ đồ</button></div>
    <MindmapViewer v-if="preview && form.root" :root="form.root" />
    <form v-else class="mindmap-editor" @submit.prevent="save">
      <section class="learning-panel editor-metadata"><BaseInput v-model="form.title" label="Tiêu đề Mindmap" :disabled="object.status !== 'draft'" required /><label class="field"><span class="field__label">Mô tả</span><textarea v-model="form.description" :disabled="object.status !== 'draft'" /></label></section>
      <section class="learning-panel"><header class="section-header"><div><h2>Nhãn các nút</h2><p>Cấu trúc và nguồn dẫn được khóa để bảo toàn tính xác thực; bạn có thể hiệu đính nhãn.</p></div></header><MindmapNodeEditor v-if="form.root" :node="form.root" :disabled="object.status !== 'draft'" /></section>
      <p v-if="error" class="form-error">{{ error }}</p><BaseButton v-if="object.status === 'draft'" type="submit" :loading="saving">Lưu thành phiên bản mới</BaseButton>
    </form>
    <section class="content-section"><header class="section-header"><div><h2>Lịch sử phiên bản</h2><p>Mỗi lần lưu tạo một snapshot bất biến.</p></div></header><div class="version-list"><span v-for="version in object.versions" :key="version.id" class="count-chip">Phiên bản {{ version.version_number }}</span></div></section>
  </template>
</template>

<style scoped>
.editor-heading { align-items: flex-end; }.editor-mode-toggle { display: flex; width: fit-content; margin-bottom: 16px; border-bottom: 1px solid var(--cds-border); }.editor-mode-toggle button { border: 0; border-bottom: 3px solid transparent; padding: 10px 18px; background: transparent; color: var(--cds-text-secondary); font-weight: 700; cursor: pointer; }.editor-mode-toggle button.active { border-bottom-color: #0056d2; color: #0056d2; }.mindmap-editor { display: grid; gap: 18px; }.editor-metadata { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }@media(max-width:700px){.editor-metadata{grid-template-columns:1fr}}
</style>
