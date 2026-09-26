<script setup>
defineProps({ status: { type: String, required: true } })
</script>

<template><span class="status-badge">{{ status === 'uploaded_pending_processing' ? 'Pending processing' : status }}</span></template>
