<script setup>
import { useRouter } from 'vue-router'
import BaseButton from '../components/BaseButton.vue'
const router = useRouter()
</script>

<template><main class="not-found"><p class="eyebrow">404</p><h1>That page is not available.</h1><p>Use the workspace navigation to continue.</p><BaseButton @click="router.push('/')">Return to FLTS</BaseButton></main></template>
