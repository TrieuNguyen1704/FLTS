<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import MathText from '../components/MathText.vue'
import SlidePlayer from '../components/SlidePlayer.vue'
import { learningObjectService } from '../services/learningObjectService'
import { toast } from '../stores/toast'

const route = useRoute(), router = useRouter()
const object = ref(null), loading = ref(true), saving = ref(false), publishing = ref(false), preview = ref(true), error = ref('')
const form = reactive({ title: '', description: '', slides: [] })
const clone = (value) => JSON.parse(JSON.stringify(value))
function hydrate(value) { if (value.type !== 'slide') throw new Error('Học liệu này không phải Slide.'); object.value = value; form.title = value.title; form.description = value.description || ''; form.slides = clone(value.slides?.slides || []) }
async function load() { loading.value = true; error.value = ''; try { hydrate((await learningObjectService.get(route.params.courseId, route.params.objectId)).learning_object) } catch (e) { error.value = e.message || 'Không thể tải Slide.' } finally { loading.value = false } }
async function save() { saving.value = true; error.value = ''; try { hydrate((await learningObjectService.update(route.params.courseId, route.params.objectId, { title: form.title.trim(), description: form.description.trim() || null, slides: form.slides })).learning_object); toast.show('Đã lưu bài giảng thành phiên bản mới.') } catch (e) { error.value = e.message || 'Không thể lưu bài giảng.' } finally { saving.value = false } }
async function publish() { if (!window.confirm('Xuất bản bài giảng này cho sinh viên?')) return; publishing.value = true; try { hydrate((await learningObjectService.publish(route.params.courseId, route.params.objectId)).learning_object); toast.show('Bài giảng đã được xuất bản.') } catch (e) { error.value = e.message || 'Không thể xuất bản bài giảng.' } finally { publishing.value = false } }
async function archive() { if (!window.confirm('Lưu trữ bài giảng này?')) return; try { hydrate((await learningObjectService.archive(route.params.courseId, route.params.objectId)).learning_object); toast.show('Bài giảng đã được lưu trữ.') } catch (e) { error.value = e.message || 'Không thể lưu trữ bài giảng.' } }
const statusLabel = (status) => status === 'draft' ? 'Bản nháp' : status === 'published' ? 'Đã xuất bản' : 'Đã lưu trữ'
onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'learning-objects', params: { courseId: route.params.courseId }, query: { type: 'slide' } })">Quay lại thư viện học liệu</button>
  <AppState v-if="loading" type="loading" title="Đang tải bài giảng" message="Đang lấy nội dung và lịch sử phiên bản." />
  <AppState v-else-if="error && !object" type="error" title="Không thể mở bài giảng" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading editor-heading"><div><span class="eyebrow">SLIDE BÀI GIẢNG</span><h1>{{ object.title }}</h1><p>Hiệu đính nội dung trình chiếu và ghi chú giảng viên trước khi xuất bản.</p></div><div class="table-actions"><span class="count-chip">Phiên bản {{ object.current_version }}</span><span class="status-chip" :class="`status-chip--${object.status}`">{{ statusLabel(object.status) }}</span><BaseButton v-if="object.status === 'draft'" :loading="publishing" @click="publish">Phê duyệt và xuất bản</BaseButton><BaseButton v-if="object.status !== 'archived'" variant="secondary" @click="archive">Lưu trữ</BaseButton></div></section>
    <div class="editor-mode-toggle"><button :class="{ active: !preview }" @click="preview = false">Biên tập</button><button :class="{ active: preview }" @click="preview = true">Trình chiếu</button></div>
    <SlidePlayer v-if="preview" :slides="form.slides" />
    <form v-else class="slide-editor" @submit.prevent="save">
      <section class="learning-panel editor-metadata"><BaseInput v-model="form.title" label="Tiêu đề bài giảng" :disabled="object.status !== 'draft'" required /><label class="field"><span class="field__label">Mô tả</span><textarea v-model="form.description" :disabled="object.status !== 'draft'" /></label></section>
      <article v-for="slide in form.slides" :key="slide.slide_number" class="slide-editor-card"><header><span class="eyebrow">SLIDE {{ slide.slide_number }}</span><strong>{{ slide.citations?.length || 0 }} nguồn dẫn</strong></header><BaseInput v-model="slide.title" label="Tiêu đề slide" :disabled="object.status !== 'draft'" required /><label class="field"><span class="field__label">Bố cục</span><select v-model="slide.layout" :disabled="object.status !== 'draft'"><option value="title">Mở đầu</option><option value="bullet_points">Ý chính</option><option value="summary">Tổng kết</option></select></label><div class="bullet-editor"><label v-for="(_, index) in slide.bullet_points" :key="index" class="field"><span class="field__label">Ý {{ index + 1 }}</span><textarea v-model="slide.bullet_points[index]" :disabled="object.status !== 'draft'" required /></label></div><label class="field"><span class="field__label">Ghi chú giảng viên</span><textarea v-model="slide.speaker_notes" :disabled="object.status !== 'draft'" required /></label><div class="slide-inline-preview"><MathText :text="slide.title" /></div></article>
      <p v-if="error" class="form-error">{{ error }}</p><BaseButton v-if="object.status === 'draft'" type="submit" :loading="saving">Lưu thành phiên bản mới</BaseButton>
    </form>
    <section class="content-section"><header class="section-header"><div><h2>Lịch sử phiên bản</h2><p>Mỗi lần lưu tạo một snapshot bất biến.</p></div></header><div class="version-list"><span v-for="version in object.versions" :key="version.id" class="count-chip">Phiên bản {{ version.version_number }}</span></div></section>
  </template>
</template>

<style scoped>
.editor-heading { align-items:flex-end }.editor-mode-toggle{display:flex;width:fit-content;margin-bottom:16px;border-bottom:1px solid var(--cds-border)}.editor-mode-toggle button{border:0;border-bottom:3px solid transparent;padding:10px 18px;background:transparent;color:var(--cds-text-secondary);font-weight:700;cursor:pointer}.editor-mode-toggle button.active{border-bottom-color:#0056d2;color:#0056d2}.slide-editor{display:grid;gap:18px}.editor-metadata{display:grid;grid-template-columns:1fr 1fr;gap:18px}.slide-editor-card{display:grid;gap:14px;padding:22px;border:1px solid var(--cds-border);background:#fff;box-shadow:var(--cds-shadow-sm)}.slide-editor-card header{display:flex;justify-content:space-between}.slide-editor-card header strong{color:var(--cds-text-muted);font-size:.78rem}.bullet-editor{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 16px}.slide-inline-preview{padding:12px;border-left:3px solid #0056d2;background:var(--cds-bg-subtle);font-weight:700}@media(max-width:700px){.editor-metadata,.bullet-editor{grid-template-columns:1fr}}
</style>
