<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppState from '../components/AppState.vue'
import SlidePlayer from '../components/SlidePlayer.vue'
import { learningObjectService } from '../services/learningObjectService'
const route=useRoute(),router=useRouter(),object=ref(null),loading=ref(true),error=ref('')
async function load(){loading.value=true;error.value='';try{const value=(await learningObjectService.get(route.params.courseId,route.params.objectId)).learning_object;if(value.type!=='slide')throw new Error('Học liệu này không phải bài giảng Slide.');object.value=value}catch(e){error.value=e.message||'Không thể tải bài giảng.'}finally{loading.value=false}}
onMounted(load)
</script>
<template><button class="back-link" @click="router.push({name:'student-course',params:{courseId:route.params.courseId}})">Quay lại khóa học</button><AppState v-if="loading" type="loading" title="Đang tải bài giảng" message="Đang chuẩn bị nội dung trình chiếu."/><AppState v-else-if="error" type="error" title="Không thể mở bài giảng" :message="error" action-label="Thử lại" @action="load"/><template v-else><section class="page-heading"><div><span class="eyebrow">BÀI GIẢNG TƯƠNG TÁC</span><h1>{{object.title}}</h1><p>{{object.description||'Nội dung chuẩn bị trước giờ học.'}}</p></div></section><SlidePlayer :slides="object.slides.slides" :show-speaker-notes="false"/></template></template>
