import { computed, reactive } from 'vue'
import { authService } from '../services/authService'

const TOKEN_KEY = 'flts_token'
const USER_KEY = 'flts_user'

function storedUser() {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
  } catch {
    return null
  }
}

const state = reactive({
  token: localStorage.getItem(TOKEN_KEY) || '',
  user: storedUser(),
  initialized: false,
})

function persist() {
  localStorage.setItem(TOKEN_KEY, state.token)
  localStorage.setItem(USER_KEY, JSON.stringify(state.user))
}

function clear() {
  state.token = ''
  state.user = null
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

async function initialize() {
  if (state.initialized) return Boolean(state.token && state.user)
  state.initialized = true
  if (!state.token) return false

  try {
    // A persisted token is untrusted until the API resolves it to the current user and role.
    state.user = (await authService.me()).user
    persist()
    return true
  } catch {
    clear()
    return false
  }
}

async function login(credentials) {
  const data = await authService.login(credentials)
  state.token = data.token
  state.user = data.user
  state.initialized = true
  persist()
  return data.user
}

async function logout() {
  try {
    if (state.token) await authService.logout()
  } catch {
    // The backend can already have invalidated this demo token after another login.
    // Local sign-out must still complete and return the user to the Login view.
  } finally {
    clear()
    state.initialized = true
  }
}

export const authStore = {
  user: computed(() => state.user),
  token: computed(() => state.token),
  isAuthenticated: computed(() => Boolean(state.token && state.user)),
  role: computed(() => state.user?.role || null),
  initialize,
  login,
  logout,
}
