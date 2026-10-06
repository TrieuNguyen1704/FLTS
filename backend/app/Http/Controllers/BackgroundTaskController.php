<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DocumentProcessingRun;
use App\Models\LearningObjectGenerationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackgroundTaskController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'lecturer', 403);

        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');

        $docRuns = DocumentProcessingRun::with(['document.course'])
            ->whereHas('document', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (DocumentProcessingRun $run) {
                $doc = $run->document;
                $course = $doc?->course;
                $status = $run->status;
                $isActive = in_array($status, ['pending', 'processing'], true);

                $stageLabels = [
                    'pending' => 'Chờ xử lý',
                    'extraction' => 'Đang trích xuất văn bản',
                    'cleaning' => 'Đang làm sạch nội dung',
                    'chunking' => 'Đang phân đoạn nội dung',
                    'embedding' => 'Đang tạo vector ngữ nghĩa',
                    'indexing' => 'Đang lưu trữ chỉ mục vector',
                    'completed' => 'Đã hoàn tất',
                    'failed' => 'Xử lý thất bại',
                ];

                return [
                    'id' => 'doc_'.$run->id,
                    'raw_id' => $run->id,
                    'type' => 'document_processing',
                    'course_id' => $course?->id,
                    'course_name' => $course?->name,
                    'course_code' => $course?->code,
                    'resource_id' => $doc?->id,
                    'title' => $doc?->original_name ?? 'Tài liệu #'.$run->teaching_document_id,
                    'status' => $status,
                    'is_active' => $isActive,
                    'stage' => $run->stage,
                    'stage_label' => $stageLabels[$run->stage] ?? ($stageLabels[$status] ?? $run->stage),
                    'started_at' => $run->started_at?->toISOString(),
                    'finished_at' => $run->finished_at?->toISOString(),
                    'created_at' => $run->created_at?->toISOString(),
                    'error_message' => is_array($run->error_detail) ? ($run->error_detail['message'] ?? null) : null,
                    'retryable' => (bool) ($run->error_detail['retryable'] ?? ($status === 'failed')),
                    'target_url' => $course ? "/lecturer/courses/{$course->id}?tab=documents" : null,
                ];
            });

        $quizRuns = LearningObjectGenerationRun::with(['learningObject.course', 'learningObject.quiz'])
            ->whereHas('learningObject', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (LearningObjectGenerationRun $run) {
                $lo = $run->learningObject;
                $course = $lo?->course;
                $status = $run->status;
                $isActive = in_array($status, ['queued', 'generating'], true);

                $stageLabels = [
                    'queued' => 'Đang chờ trong hàng đợi',
                    'generating' => 'Đang phân tích tài liệu và sinh câu hỏi',
                    'completed' => 'Đã tạo Quiz thành công',
                    'failed' => 'Tạo Quiz thất bại',
                ];

                return [
                    'id' => 'quiz_'.$run->id,
                    'raw_id' => $run->id,
                    'type' => 'quiz_generation',
                    'course_id' => $course?->id,
                    'course_name' => $course?->name,
                    'course_code' => $course?->code,
                    'resource_id' => $lo?->id,
                    'title' => $lo?->title ?? 'Quiz #'.$run->learning_object_id,
                    'status' => $status,
                    'is_active' => $isActive,
                    'stage' => $status,
                    'stage_label' => $stageLabels[$status] ?? $status,
                    'started_at' => $run->started_at?->toISOString(),
                    'finished_at' => $run->finished_at?->toISOString(),
                    'created_at' => $run->created_at?->toISOString(),
                    'error_message' => $run->error_message,
                    'retryable' => $status === 'failed' && $lo && !$lo->quiz,
                    'target_url' => $course ? "/lecturer/courses/{$course->id}?tab=quizzes" : null,
                ];
            });

        $tasks = $docRuns->concat($quizRuns)->sortByDesc(fn ($t) => $t['created_at'])->values()->take(25);
        $activeCount = $tasks->filter(fn ($t) => $t['is_active'])->count();

        return response()->json([
            'tasks' => $tasks,
            'active_count' => $activeCount,
        ]);
    }
}
