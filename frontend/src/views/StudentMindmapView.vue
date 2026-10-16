<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import MindmapViewer from '../components/MindmapViewer.vue'
import { learningObjectService } from '../services/learningObjectService'
const route = useRoute(), router = useRouter(), object = ref(null), loading = ref(true), error = ref('')
async function load(){loading.value=true;error.value='';try{const value=(await learningObjectService.get(route.params.courseId,route.params.objectId)).learning_object;if(value.type!=='mindmap')throw new Error('Học liệu này không phải Mindmap.');object.value=value}catch(e){error.value=e.message||'Không thể tải Mindmap.'}finally{loading.value=false}}
onMounted(load)
</script>
<template><button class="back-link" @click="router.push({name:'student-course',params:{courseId:route.params.courseId}})">Quay lại khóa học</button><AppState v-if="loading" type="loading" title="Đang tải Mindmap" message="Đang chuẩn bị sơ đồ kiến thức."/><AppState v-else-if="error" type="error" title="Không thể mở Mindmap" :message="error" action-label="Thử lại" @action="load"/><template v-else><section class="page-heading"><div><span class="eyebrow">SƠ ĐỒ TƯ DUY</span><h1>{{object.title}}</h1><p>{{object.description||'Khám phá cấu trúc kiến thức của bài học.'}}</p></div></section><MindmapViewer :root="object.mindmap.root"/></template></template>
