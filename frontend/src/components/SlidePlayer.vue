<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import MathText from './MathText.vue'

const props = defineProps({ slides: { type: Array, required: true }, showSpeakerNotes: { type: Boolean, default: true } })
const index = ref(0)
const notesOpen = ref(false)
const player = ref(null)
const current = computed(() => props.slides[index.value] || null)
const progress = computed(() => props.slides.length ? ((index.value + 1) / props.slides.length) * 100 : 0)
function previous() { if (index.value > 0) index.value -= 1 }
function next() { if (index.value < props.slides.length - 1) index.value += 1 }
function onKey(event) { if (event.key === 'ArrowLeft') previous(); if (event.key === 'ArrowRight') next() }
async function fullscreen() { if (player.value?.requestFullscreen) await player.value.requestFullscreen() }
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section ref="player" class="slide-player" tabindex="0">
    <div class="slide-progress"><span :style="{ width: `${progress}%` }" /></div>
    <article v-if="current" class="slide-stage">
      <span class="slide-number">Slide {{ index + 1 }} / {{ slides.length }}</span>
      <h2><MathText :text="current.title" /></h2>
      <ul><li v-for="(point, pointIndex) in current.bullet_points" :key="pointIndex"><MathText :text="point" /></li></ul>
      <footer><span>{{ current.citations?.[0]?.document_name }}</span><span>{{ current.citations?.[0]?.source_locator }}</span></footer>
    </article>
    <aside v-if="notesOpen && current?.speaker_notes" class="speaker-notes"><strong>Ghi chú giảng viên</strong><MathText :text="current.speaker_notes" /></aside>
    <nav class="slide-controls">
      <button type="button" :disabled="index === 0" @click="previous">Slide trước</button>
      <div>
        <button v-if="showSpeakerNotes && current?.speaker_notes" type="button" @click="notesOpen = !notesOpen">{{ notesOpen ? 'Ẩn ghi chú' : 'Hiện ghi chú' }}</button>
        <button type="button" @click="fullscreen">Toàn màn hình</button>
      </div>
      <button type="button" :disabled="index === slides.length - 1" @click="next">Slide sau</button>
    </nav>
  </section>
</template>

<style scoped>
.slide-player { background: #eef3f8; border: 1px solid var(--cds-border); min-height: 600px; display: flex; flex-direction: column; outline: none; }
.slide-player:fullscreen { width: 100vw; height: 100vh; padding: 4vh 5vw; }
.slide-progress { height: 5px; background: #cbd5e1; }.slide-progress span { display: block; height: 100%; background: #0056d2; transition: width .2s ease; }
.slide-stage { width: min(900px, calc(100% - 48px)); min-height: 430px; margin: 28px auto 18px; padding: clamp(30px, 6vw, 70px); background: #fff; box-shadow: 0 12px 35px rgba(15,23,42,.14); display: flex; flex-direction: column; }
.slide-number { color: #0056d2; font-weight: 800; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
.slide-stage h2 { margin: 20px 0 26px; font-size: clamp(1.7rem, 4vw, 2.8rem); line-height: 1.15; }
.slide-stage ul { display: grid; gap: 18px; padding-left: 24px; font-size: clamp(1rem, 2vw, 1.3rem); line-height: 1.5; }
.slide-stage footer { display: flex; justify-content: space-between; margin-top: auto; padding-top: 24px; border-top: 1px solid var(--cds-border); color: var(--cds-text-muted); font-size: .75rem; }
.speaker-notes { width: min(900px, calc(100% - 48px)); margin: 0 auto 16px; padding: 16px 20px; border-left: 4px solid #0056d2; background: #fff; color: var(--cds-text-secondary); }
.speaker-notes strong { display: block; margin-bottom: 6px; color: var(--cds-text-primary); }
.slide-controls { display: flex; justify-content: space-between; gap: 10px; margin-top: auto; padding: 16px 24px; background: #fff; border-top: 1px solid var(--cds-border); }
.slide-controls div { display: flex; gap: 8px; }.slide-controls button { border: 1px solid #b8c4d2; padding: 9px 14px; background: #fff; color: #0056d2; font-weight: 700; cursor: pointer; }.slide-controls button:disabled { opacity: .45; cursor: default; }
@media (max-width: 650px) { .slide-player { min-height: 520px; }.slide-stage { width: calc(100% - 24px); min-height: 360px; margin-top: 12px; padding: 24px; }.slide-controls { padding: 12px; }.slide-controls div { display: none; } }
</style>
