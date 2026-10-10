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
  loading.value = true
  error.value = ''
  try {
    users.value = (await adminService.listUsers(query.value.trim())).users
  } catch (requestError) {
    error.value = requestError.message || 'Không thể tải danh sách tài khoản.'
  } finally {
    loading.value = false
  }
}

function openEdit(user) {
  selectedUser.value = user
  form.role = user.role
  form.account_status = user.account_status
}

async function saveUser() {
  if (!selectedUser.value) return
  saving.value = true
  try {
    const { user } = await adminService.updateUser(selectedUser.value.id, {
      role: form.role,
      account_status: form.account_status
    })
    users.value = users.value.map((item) => item.id === user.id ? user : item)
    selectedUser.value = null
    toast.show('Cập nhật tài khoản thành công.')
  } catch (requestError) {
    toast.show(requestError.message || 'Không thể cập nhật tài khoản.', 'error')
  } finally {
    saving.value = false
  }
}

function roleLabel(role) {
  if (role === 'admin') return 'Quản trị viên'
  if (role === 'lecturer') return 'Giảng viên'
  if (role === 'student') return 'Sinh viên'
  return role
}

function statusLabel(status) {
  return status === 'active' ? 'Hoạt động' : 'Tạm khóa'
}

onMounted(loadUsers)
</script>

<template>
  <section class="page-heading">
    <div>
      <span class="eyebrow">QUẢN TRỊ VIÊN HỆ THỐNG</span>
      <h1>Quản lý tài khoản</h1>
      <p>Xem danh sách tài khoản đã đăng ký, phân quyền vai trò và quản lý trạng thái truy cập toàn hệ thống.</p>
    </div>
  </section>
  <form class="toolbar" @submit.prevent="loadUsers">
    <input v-model="query" placeholder="Tìm kiếm theo tên hoặc email..." aria-label="Tìm kiếm tài khoản" />
    <BaseButton type="submit" variant="secondary">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      Tìm kiếm
    </BaseButton>
  </form>
  <AppState v-if="loading" type="loading" title="Đang tải danh sách tài khoản" message="Đang lấy dữ liệu từ hệ thống quản trị." />
  <AppState v-else-if="error" type="error" title="Không thể tải danh sách tài khoản" :message="error" action-label="Thử lại" @action="loadUsers" />
  <div v-else-if="users.length" class="document-table-wrap">
    <table class="document-table admin-table">
      <thead>
        <tr>
          <th>Họ và tên</th>
          <th>Địa chỉ Email</th>
          <th>Vai trò</th>
          <th>Trạng thái</th>
          <th>Ngày tạo</th>
          <th aria-label="Thao tác" />
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td><strong>{{ user.name }}</strong></td>
          <td>{{ user.email }}</td>
          <td><span class="role-chip">{{ roleLabel(user.role) }}</span></td>
          <td><span :class="['status-chip', `status-chip--${user.account_status}`]">{{ statusLabel(user.account_status) }}</span></td>
          <td>{{ new Date(user.created_at).toLocaleDateString('vi-VN') }}</td>
          <td>
            <BaseButton v-if="user.id !== authStore.user.value?.id" variant="secondary" @click="openEdit(user)">Quản lý</BaseButton>
            <span v-else class="muted">Tài khoản hiện tại</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <AppState v-else title="Không tìm thấy tài khoản nào" message="Thử tìm kiếm với tên hoặc email khác." />
  <AppModal
    v-model="selectedUser"
    title="Cập nhật tài khoản"
    confirm-label="Lưu thay đổi"
    :loading="saving"
    @confirm="saveUser"
  >
    <p class="muted">{{ selectedUser?.email }}</p>
    <label class="field">
      <span class="field__label">Vai trò</span>
      <select v-model="form.role">
        <option value="lecturer">Giảng viên</option>
        <option value="student">Sinh viên</option>
        <option value="admin">Quản trị viên</option>
      </select>
    </label>
    <label class="field">
      <span class="field__label">Trạng thái tài khoản</span>
      <select v-model="form.account_status">
        <option value="active">Hoạt động</option>
        <option value="suspended">Tạm khóa</option>
      </select>
    </label>
  </AppModal>
</template>
