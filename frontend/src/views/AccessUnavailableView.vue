<script setup>
import { useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import BaseButton from '../components/BaseButton.vue'
import { authStore } from '../stores/auth'

const router = useRouter()
async function signOut() { await authStore.logout(); router.push({ name: 'login' }) }
</script>

<template><AppState type="empty" title="No workspace is available for this role" message="Administrator account data exists in Sprint 1, but an administrator management workspace has not been implemented. This screen does not represent a completed user story." action-label="Sign out" @action="signOut" /></template>
