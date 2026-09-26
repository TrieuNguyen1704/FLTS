import { createRouter, createWebHistory } from 'vue-router'
import { authStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import PasswordRecoveryView from '../views/PasswordRecoveryView.vue'
import AdminDashboardView from '../views/AdminDashboardView.vue'
import LecturerDashboardView from '../views/LecturerDashboardView.vue'
import CourseManagementView from '../views/CourseManagementView.vue'
import CourseDetailView from '../views/CourseDetailView.vue'
import StudentDashboardView from '../views/StudentDashboardView.vue'
import AccessUnavailableView from '../views/AccessUnavailableView.vue'
import NotFoundView from '../views/NotFoundView.vue'

const routes = [
  { path: '/', redirect: () => authStore.role.value === 'student' ? '/student/dashboard' : authStore.role.value === 'admin' ? '/admin/dashboard' : '/lecturer/dashboard' },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
  { path: '/password-recovery', name: 'password-recovery', component: PasswordRecoveryView, meta: { guestOnly: true } },
  { path: '/reset-password', name: 'reset-password', component: PasswordRecoveryView, meta: { guestOnly: true } },
  { path: '/lecturer/dashboard', name: 'lecturer-dashboard', component: LecturerDashboardView, meta: { requiresAuth: true, roles: ['lecturer'] } },
  { path: '/lecturer/courses', name: 'course-management', component: CourseManagementView, meta: { requiresAuth: true, roles: ['lecturer'] } },
  { path: '/lecturer/courses/:id', name: 'course-detail', component: CourseDetailView, meta: { requiresAuth: true, roles: ['lecturer'] } },
  { path: '/student/dashboard', name: 'student-dashboard', component: StudentDashboardView, meta: { requiresAuth: true, roles: ['student'] } },
  { path: '/admin/dashboard', name: 'admin-dashboard', component: AdminDashboardView, meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/access-unavailable', name: 'access-unavailable', component: AccessUnavailableView, meta: { requiresAuth: true } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
]

const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) })

function homeForRole(role) {
  if (role === 'student') return { name: 'student-dashboard' }
  if (role === 'lecturer') return { name: 'lecturer-dashboard' }
  if (role === 'admin') return { name: 'admin-dashboard' }
  return { name: 'access-unavailable' }
}

router.beforeEach(async (to) => {
  // Restore a saved session before deciding a protected route; backend middleware remains the security authority.
  const authenticated = await authStore.initialize()

  if (to.meta.guestOnly && authenticated) return homeForRole(authStore.role.value)
  if (!to.meta.requiresAuth) return true
  if (!authenticated) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.roles && !to.meta.roles.includes(authStore.role.value)) return homeForRole(authStore.role.value)
  return true
})

export default router
