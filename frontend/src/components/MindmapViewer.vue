<script setup>
import { computed, ref } from 'vue'
import MathText from './MathText.vue'

const props = defineProps({ root: { type: Object, required: true } })
const collapsed = ref(new Set())
const scale = ref(1)
const offset = ref({ x: 30, y: 30 })
const dragging = ref(false)
const dragStart = ref({ x: 0, y: 0 })
const selected = ref(null)

const layout = computed(() => {
  const nodes = []
  const edges = []
  let row = 0
  function visit(node, depth = 0, parent = null) {
    const item = { ...node, x: depth * 260, y: row * 112 }
    nodes.push(item)
    if (parent) edges.push({ from: parent, to: item })
    row += 1
    if (!collapsed.value.has(node.id)) (node.children || []).forEach((child) => visit(child, depth + 1, item))
  }
  visit(props.root)
  return { nodes, edges, width: Math.max(900, ...nodes.map((n) => n.x + 240)), height: Math.max(520, row * 112 + 40) }
})

function toggle(node) {
  if (!node.children?.length) return
  const next = new Set(collapsed.value)
  next.has(node.id) ? next.delete(node.id) : next.add(node.id)
  collapsed.value = next
}
function zoom(delta) { scale.value = Math.min(1.8, Math.max(.55, scale.value + delta)) }
function reset() { scale.value = 1; offset.value = { x: 30, y: 30 }; collapsed.value = new Set() }
function startPan(event) { dragging.value = true; dragStart.value = { x: event.clientX - offset.value.x, y: event.clientY - offset.value.y } }
function pan(event) { if (dragging.value) offset.value = { x: event.clientX - dragStart.value.x, y: event.clientY - dragStart.value.y } }
function stopPan() { dragging.value = false }
</script>

<template>
  <section class="mindmap-viewer">
    <div class="mindmap-toolbar">
      <button type="button" @click="zoom(.15)">Phóng to</button>
      <button type="button" @click="zoom(-.15)">Thu nhỏ</button>
      <button type="button" @click="reset">Đặt lại</button>
      <span>Kéo nền để di chuyển; chọn nút để xem nguồn.</span>
    </div>
    <div class="mindmap-canvas" @pointerdown.self="startPan" @pointermove="pan" @pointerup="stopPan" @pointerleave="stopPan">
      <svg :viewBox="`0 0 ${layout.width} ${layout.height}`" :style="{ transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})` }">
        <path v-for="edge in layout.edges" :key="`${edge.from.id}-${edge.to.id}`" :d="`M ${edge.from.x + 210} ${edge.from.y + 36} C ${edge.from.x + 235} ${edge.from.y + 36}, ${edge.to.x - 25} ${edge.to.y + 36}, ${edge.to.x} ${edge.to.y + 36}`" />
        <g v-for="node in layout.nodes" :key="node.id" :transform="`translate(${node.x}, ${node.y})`" class="mindmap-node" @click.stop="selected = node">
          <rect width="210" height="72" rx="10" />
          <foreignObject x="12" y="10" width="178" height="52"><div class="mindmap-node__label"><MathText :text="node.label" /></div></foreignObject>
          <g v-if="node.children?.length" class="mindmap-fold" transform="translate(198, 26)" @click.stop="toggle(node)">
            <circle r="11" /><text text-anchor="middle" dominant-baseline="central">{{ collapsed.has(node.id) ? '+' : '−' }}</text>
          </g>
        </g>
      </svg>
    </div>
    <aside v-if="selected" class="mindmap-source">
      <div><strong>Nguồn cho nút đã chọn</strong><button type="button" @click="selected = null">Đóng</button></div>
      <MathText :text="selected.label" />
      <ul><li v-for="citation in selected.citations" :key="citation.vector_id">{{ citation.document_name }} · {{ citation.source_locator || citation.vector_id }}</li></ul>
    </aside>
  </section>
</template>

<style scoped>
.mindmap-viewer { border: 1px solid var(--cds-border); background: #fff; box-shadow: var(--cds-shadow-sm); }
.mindmap-toolbar { display: flex; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--cds-border); }
.mindmap-toolbar button { border: 1px solid var(--cds-border); padding: 7px 12px; background: #fff; color: var(--cds-blue-primary); font-weight: 700; cursor: pointer; }
.mindmap-toolbar span { margin-left: auto; color: var(--cds-text-secondary); font-size: .78rem; }
.mindmap-canvas { height: 620px; overflow: hidden; background: radial-gradient(circle, #dce5f0 1px, transparent 1px); background-size: 22px 22px; cursor: grab; touch-action: none; }
.mindmap-canvas:active { cursor: grabbing; }
svg { width: 100%; height: 100%; overflow: visible; transform-origin: 0 0; transition: transform .12s ease; }
path { fill: none; stroke: #94a3b8; stroke-width: 2; }
.mindmap-node { cursor: pointer; }
.mindmap-node rect { fill: #fff; stroke: #b9c7d8; stroke-width: 1.5; filter: drop-shadow(0 2px 4px rgba(15, 23, 42, .08)); }
.mindmap-node:hover rect { stroke: #0056d2; }
.mindmap-node__label { height: 100%; display: flex; align-items: center; color: #0f172a; font-size: 13px; font-weight: 650; line-height: 1.3; overflow: hidden; }
.mindmap-fold circle { fill: #0056d2; }.mindmap-fold text { fill: white; font-size: 16px; }
.mindmap-source { padding: 16px; border-top: 1px solid var(--cds-border); background: var(--cds-bg-subtle); }
.mindmap-source > div { display: flex; justify-content: space-between; }.mindmap-source button { border: 0; background: transparent; color: #0056d2; cursor: pointer; }
.mindmap-source ul { margin-bottom: 0; color: var(--cds-text-secondary); font-size: .8rem; }
@media (max-width: 700px) { .mindmap-toolbar span { display: none; }.mindmap-canvas { height: 480px; } }
</style>
