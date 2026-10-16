<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import FlashcardPlayer from '../components/FlashcardPlayer.vue'
import { learningObjectService } from '../services/learningObjectService'

const route = useRoute()
const router = useRouter()
const object = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const value = (await learningObjectService.get(route.params.courseId, route.params.objectId)).learning_object
    if (value.type !== 'flashcard') throw new Error('Học liệu này không phải bộ Flashcard.')
    object.value = value
  } catch (requestError) { error.value = requestError.message || 'Không thể tải bộ Flashcard.' }
  finally { loading.value = false }
}

onMounted(load)
</script>

<template>
  <button class="back-link" @click="router.push({ name: 'student-course', params: { courseId: route.params.courseId } })">Quay lại khóa học</button>
  <AppState v-if="loading" type="loading" title="Đang tải Flashcard" message="Đang chuẩn bị nội dung ôn tập." />
  <AppState v-else-if="error" type="error" title="Không thể mở Flashcard" :message="error" action-label="Thử lại" @action="load" />
  <template v-else>
    <section class="page-heading">
      <div><span class="eyebrow">FLASHCARD · {{ object.total_cards }} THẺ</span><h1>{{ object.title }}</h1><p>{{ object.description || 'Ôn tập các khái niệm trọng tâm của khóa học.' }}</p></div>
    </section>
    <FlashcardPlayer :cards="object.flashcard.cards" />
  </template>
</template>
