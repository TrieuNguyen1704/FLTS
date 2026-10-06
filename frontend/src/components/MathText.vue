<script setup>
import { computed } from 'vue'
import katex from 'katex'
import 'katex/dist/katex.min.css'

const props = defineProps({
  text: {
    type: String,
    default: '',
  },
})

// Normalizes legacy font artifacts and renders LaTeX ($...$) or math expressions with KaTeX
const renderedHtml = computed(() => {
  if (!props.text) return ''

  // 1. Normalize legacy font artifacts (e.g. Word/MathType Symbol font decoded as ANSI/CP1252)
  let clean = props.text
    .replace(/(?<=[A-Za-z0-9\)\}\| ])\s*È\s*(?=[A-Za-z0-9\(\{\| ])/g, ' ∪ ')
    .replace(/(?<=[A-Za-z0-9\)\}\| ])\s*Ç\s*(?=[A-Za-z0-9\(\{\| ])/g, ' ∩ ')
    .replace(/(?<=[A-Za-z0-9\)\}\| ])\s*Î\s*(?=[A-Za-z0-9\(\{\| ])/g, ' ∈ ')
    .replace(/(?<=[A-Za-z0-9\)\}\| ])\s*Ï\s*(?=[A-Za-z0-9\(\{\| ])/g, ' ∉ ')
    .replace(/(?<=[A-Za-z0-9\)\}\| ])\s*Ì\s*(?=[A-Za-z0-9\(\{\| ])/g, ' ⊂ ')
    .replace(/(?<=[A-Za-z0-9\)\}\| ])\s*Í\s*(?=[A-Za-z0-9\(\{\| ])/g, ' ⊆ ')

  // 2. Check if text contains LaTeX dollar delimiters ($...$ or $$...$$)
  if (clean.includes('$')) {
    const parts = clean.split(/(\$\$[\s\S]*?\$\$|\$[^\$]+?\$)/g)
    return parts
      .map((part) => {
        if (part.startsWith('$$') && part.endsWith('$$')) {
          const math = part.slice(2, -2)
          try {
            return katex.renderToString(math, { displayMode: true, throwOnError: false })
          } catch {
            return escapeHtml(part)
          }
        }
        if (part.startsWith('$') && part.endsWith('$')) {
          const math = part.slice(1, -1)
          try {
            return katex.renderToString(math, { displayMode: false, throwOnError: false })
          } catch {
            return escapeHtml(part)
          }
        }
        return escapeHtml(part)
      })
      .join('')
  }

  // 3. If there are no $ delimiters:
  // Check if it contains Vietnamese prose words
  const hasVietnamese = /[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i.test(clean)

  // If it's a pure math formula (e.g. "|A ∪ B| = |A| + |B| - |A ∩ B|") without Vietnamese text
  if (!hasVietnamese && (/[∪∩∈∉⊂⊆∅∑∏√∫]/.test(clean) || (/\|[A-Z]/.test(clean) && clean.includes('=')))) {
    let latex = clean
      .replace(/∪/g, '\\cup ')
      .replace(/∩/g, '\\cap ')
      .replace(/∈/g, '\\in ')
      .replace(/∉/g, '\\notin ')
      .replace(/⊂/g, '\\subset ')
      .replace(/⊆/g, '\\subseteq ')
      .replace(/∅/g, '\\emptyset ')

    try {
      return katex.renderToString(latex, { displayMode: false, throwOnError: false })
    } catch {
      return escapeHtml(clean)
    }
  }

  // Otherwise, return escaped HTML (the normalized Unicode symbols like ∪, ∩ will render naturally)
  return escapeHtml(clean)
})

function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}
</script>

<template>
  <span class="math-text" v-html="renderedHtml"></span>
</template>

<style scoped>
.math-text {
  display: inline;
  word-break: break-word;
}
:deep(.katex) {
  font-size: 1.05em;
  line-height: 1.2;
}
</style>
