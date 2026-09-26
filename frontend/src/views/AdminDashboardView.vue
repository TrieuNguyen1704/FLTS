<script setup>
import { onMounted, reactive, ref } from 'vue'
import AppModal from '../components/AppModal.vue'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import { adminService } from '../services/adminService'
import { authStore } from '../stores/auth'
import { toast } from '../stores/toast'

const users = ref([])
const loading = ref(true)
const error = ref('')
const query = ref('')
const selectedUser = ref(null)
const saving = ref(false)
const form = reactive({ role: '', account_status: '' })

async function loadUsers() {
  loading.value = true; error.value = ''
  try { users.value = (await adminService.listUsers(query.value.trim())).users } catch (requestError) { error.value = requestError.message } finally { loading.value = false }
}
function openEdit(user) { selectedUser.value = user; form.role = user.role; form.account_status = user.account_status }
async function saveUser() {
  if (!selectedUser.value) return
  saving.value = true
  try {
    const { user } = await adminService.updateUser(selectedUser.value.id, { role: form.role, account_status: form.account_status })
    users.value = users.value.map((item) => item.id === user.id ? user : item)
    selectedUser.value = null
    toast.show('Account updated.')
  } catch (requestError) { toast.show(requestError.message, 'error') } finally { saving.value = false }
}
onMounted(loadUsers)
</script>

<template>
  <section class="page-heading"><div><p class="eyebrow">ADMINISTRATOR WORKSPACE</p><h1>Account management</h1><p>View registered accounts and update roles or access status. Changes are enforced by the Laravel API.</p></div></section>
  <section class="notice-banner"><strong>Access control</strong><span>Suspending an account immediately revokes its active demo token. You cannot change your own Administrator role or status.</span></section>
  <form class="toolbar" @submit.prevent="loadUsers"><input v-model="query" placeholder="Search by name or email" aria-label="Search accounts" /><BaseButton type="submit" variant="secondary">Search</BaseButton></form>
  <AppState v-if="loading" type="loading" title="Loading accounts" message="Retrieving accounts from the protected Admin API." />
  <AppState v-else-if="error" type="error" title="Unable to load accounts" :message="error" action-label="Try again" @action="loadUsers" />
  <div v-else-if="users.length" class="document-table-wrap"><table class="document-table admin-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th /></tr></thead><tbody><tr v-for="user in users" :key="user.id"><td><strong>{{ user.name }}</strong></td><td>{{ user.email }}</td><td><span class="role-chip">{{ user.role }}</span></td><td><span :class="['status-chip', `status-chip--${user.account_status}`]">{{ user.account_status }}</span></td><td>{{ new Date(user.created_at).toLocaleDateString() }}</td><td><BaseButton v-if="user.id !== authStore.user.value?.id" variant="secondary" @click="openEdit(user)">Manage</BaseButton><span v-else class="muted">Current account</span></td></tr></tbody></table></div>
  <AppState v-else title="No accounts found" message="Try a different name or email search." />
  <AppModal v-model="selectedUser" title="Manage account" confirm-label="Save changes" :loading="saving" @confirm="saveUser"><p class="muted">{{ selectedUser?.email }}</p><label class="field"><span class="field__label">Role</span><select v-model="form.role"><option value="lecturer">Lecturer</option><option value="student">Student</option><option value="admin">Administrator</option></select></label><label class="field"><span class="field__label">Account status</span><select v-model="form.account_status"><option value="active">Active</option><option value="suspended">Suspended</option></select></label></AppModal>
</template>
