import { reactive } from 'vue'

const state = reactive({ message: '', tone: 'success', visible: false })
let timeoutId

function show(message, tone = 'success') {
  state.message = message
  state.tone = tone
  state.visible = true
  clearTimeout(timeoutId)
  timeoutId = window.setTimeout(() => { state.visible = false }, 4200)
}

export const toast = { state, show, hide: () => { state.visible = false } }
