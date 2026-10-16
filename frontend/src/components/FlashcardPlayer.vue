<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import MathText from './MathText.vue'

const props = defineProps({
  cards: { type: Array, default: () => [] },
})

const currentIndex = ref(0)
const flipped = ref(false)
const reviewState = ref({})
const currentCard = computed(() => props.cards[currentIndex.value] || null)
const reviewedCount = computed(() => Object.keys(reviewState.value).length)

watch(() => props.cards.length, (length) => {
  if (!length) currentIndex.value = 0
  else if (currentIndex.value >= length) currentIndex.value = length - 1
})

function goTo(index) {
  if (!props.cards.length) return
  currentIndex.value = Math.min(Math.max(index, 0), props.cards.length - 1)
  flipped.value = false
}

function toggleCard() {
  if (currentCard.value) flipped.value = !flipped.value
}

function mark(value) {
  if (!currentCard.value) return
  reviewState.value = { ...reviewState.value, [currentIndex.value]: value }
  if (currentIndex.value < props.cards.length - 1) goTo(currentIndex.value + 1)
}

function handleKeyboard(event) {
  const tag = event.target?.tagName
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || event.target?.isContentEditable) return
  if (event.code === 'Space') {
    event.preventDefault()
    toggleCard()
  } else if (event.key === 'ArrowLeft') goTo(currentIndex.value - 1)
  else if (event.key === 'ArrowRight') goTo(currentIndex.value + 1)
}

onMounted(() => window.addEventListener('keydown', handleKeyboard))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeyboard))
</script>

<template>
  <section v-if="currentCard" class="flashcard-player" aria-label="Trình học Flashcard">
    <div class="flashcard-player__progress" aria-live="polite">
      <span>Thẻ {{ currentIndex + 1 }} / {{ cards.length }}</span>
      <span>Đã đánh dấu {{ reviewedCount }} thẻ</span>
    </div>

    <button
      class="flashcard"
      :class="{ 'flashcard--flipped': flipped }"
      type="button"
      :aria-label="flipped ? 'Xem mặt trước' : 'Xem đáp án'"
      @click="toggleCard"
    >
      <span class="flashcard__inner">
        <span class="flashcard__face flashcard__front">
          <small>Khái niệm / câu hỏi</small>
          <MathText :text="currentCard.front" />
          <em>Nhấn vào thẻ hoặc phím Space để xem đáp án</em>
        </span>
        <span class="flashcard__face flashcard__back">
          <small>Đáp án</small>
          <MathText :text="currentCard.back" />
          <span v-if="currentCard.hint" class="flashcard__hint">
            <strong>Gợi ý ghi nhớ:</strong> <MathText :text="currentCard.hint" />
          </span>
        </span>
      </span>
    </button>

    <div v-if="flipped && currentCard.citations?.length" class="flashcard-sources">
      <strong>Nguồn tham khảo</strong>
      <span v-for="citation in currentCard.citations" :key="citation.vector_id">
        {{ citation.document_name || 'Tài liệu nguồn' }} · {{ citation.source_locator || citation.vector_id }}
      </span>
    </div>

    <div class="flashcard-player__controls">
      <button type="button" class="button button--secondary" :disabled="currentIndex === 0" @click="goTo(currentIndex - 1)">Thẻ trước</button>
      <div class="flashcard-player__review">
        <button type="button" class="review-button review-button--again" @click="mark('again')">Cần ôn lại</button>
        <button type="button" class="review-button review-button--known" @click="mark('known')">Đã hiểu</button>
      </div>
      <button type="button" class="button button--secondary" :disabled="currentIndex === cards.length - 1" @click="goTo(currentIndex + 1)">Thẻ sau</button>
    </div>
  </section>
</template>

<style scoped>
.flashcard-player { max-width: 820px; margin: 0 auto; }
.flashcard-player__progress { display: flex; justify-content: space-between; margin-bottom: 12px; color: var(--cds-text-secondary); font-size: .85rem; }
.flashcard { width: 100%; min-height: 340px; padding: 0; border: 0; background: transparent; perspective: 1200px; cursor: pointer; text-align: left; }
.flashcard__inner { position: relative; display: block; width: 100%; min-height: 340px; transition: transform .45s ease; transform-style: preserve-3d; }
.flashcard--flipped .flashcard__inner { transform: rotateY(180deg); }
.flashcard__face { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; gap: 22px; padding: clamp(28px, 6vw, 64px); border: 1px solid #cbd5e1; border-radius: 16px; background: #fff; box-shadow: 0 8px 28px rgba(15, 23, 42, .08); backface-visibility: hidden; color: var(--cds-text-primary); font-size: clamp(1.1rem, 2.2vw, 1.55rem); line-height: 1.55; }
.flashcard__face small { color: #0056d2; font-size: .75rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.flashcard__face em { margin-top: auto; color: var(--cds-text-secondary); font-size: .78rem; font-style: normal; }
.flashcard__back { transform: rotateY(180deg); border-top: 5px solid #0056d2; }
.flashcard__hint { padding: 12px 14px; border-left: 3px solid #0056d2; background: #f0f6ff; font-size: .88rem; }
.flashcard-sources { display: flex; flex-wrap: wrap; gap: 8px 14px; margin: 12px 0; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; color: var(--cds-text-secondary); font-size: .78rem; }
.flashcard-sources strong { color: var(--cds-text-primary); }
.flashcard-player__controls { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 12px; margin-top: 18px; }
.flashcard-player__controls > :last-child { justify-self: end; }
.flashcard-player__review { display: flex; gap: 8px; }
.review-button { border: 1px solid; border-radius: 6px; padding: 10px 16px; background: #fff; font-weight: 700; cursor: pointer; }
.review-button--again { border-color: #dc2626; color: #b91c1c; }
.review-button--known { border-color: #15803d; color: #166534; }
@media (max-width: 680px) {
  .flashcard, .flashcard__inner { min-height: 390px; }
  .flashcard-player__controls { grid-template-columns: 1fr 1fr; }
  .flashcard-player__review { grid-column: 1 / -1; grid-row: 1; }
  .review-button { flex: 1; }
}
</style>
