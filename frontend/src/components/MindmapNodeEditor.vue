<script setup>
defineOptions({ name: 'MindmapNodeEditor' })
defineProps({ node: { type: Object, required: true }, disabled: Boolean, level: { type: Number, default: 0 } })
</script>

<template>
  <div class="node-editor" :style="{ marginLeft: `${Math.min(level, 4) * 20}px` }">
    <label class="field"><span class="field__label">{{ level === 0 ? 'Nút gốc' : `Nút cấp ${level}` }}</span><input v-model="node.label" :disabled="disabled" maxlength="500" required /></label>
    <small>{{ node.citations?.length || 0 }} nguồn dẫn được bảo toàn</small>
  </div>
  <MindmapNodeEditor v-for="child in node.children || []" :key="child.id" :node="child" :disabled="disabled" :level="level + 1" />
</template>

<style scoped>
.node-editor { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: end; padding: 10px 0; border-bottom: 1px solid var(--cds-border-light); }
.node-editor small { padding-bottom: 11px; color: var(--cds-text-muted); }
@media (max-width: 650px) { .node-editor { grid-template-columns: 1fr; margin-left: 0 !important; }.node-editor small { padding: 0; } }
</style>
