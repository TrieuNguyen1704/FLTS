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

        $generationRuns = LearningObjectGenerationRun::with(['learningObject.course', 'learningObject.quiz', 'learningObject.latestVersion'])
            ->whereHas('learningObject', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (LearningObjectGenerationRun $run) {
                $lo = $run->learningObject;
                $course = $lo?->course;
                $status = $run->status;
                $isActive = in_array($status, ['queued', 'generating'], true);

                $objectType = $lo?->type ?? 'quiz';
                $objectLabel = ['quiz' => 'Quiz', 'flashcard' => 'Flashcard', 'mindmap' => 'Mindmap', 'slide' => 'Slide'][$objectType] ?? 'Learning object';
                $generationLabels = [
                    'quiz' => 'Đang phân tích tài liệu và sinh câu hỏi',
                    'flashcard' => 'Đang tạo bộ thẻ ghi nhớ',
                    'mindmap' => 'Đang xây dựng sơ đồ kiến thức',
                    'slide' => 'Đang biên soạn bài giảng',
                ];
                $editorPaths = ['quiz' => 'learning-objects', 'flashcard' => 'flashcards', 'mindmap' => 'mindmaps', 'slide' => 'slides'];
                $stageLabels = [
                    'queued' => 'Đang chờ trong hàng đợi',
                    'generating' => $generationLabels[$objectType] ?? 'Đang tạo học liệu',
                    'completed' => "Đã tạo {$objectLabel} thành công",
                    'failed' => "Tạo {$objectLabel} thất bại",
                ];

                return [
                    'id' => $objectType.'_'.$run->id,
                    'raw_id' => $run->id,
                    'type' => $objectType.'_generation',
                    'course_id' => $course?->id,
                    'course_name' => $course?->name,
                    'course_code' => $course?->code,
                    'resource_id' => $lo?->id,
                    'title' => $lo?->title ?? $objectLabel.' #'.$run->learning_object_id,
                    'status' => $status,
                    'is_active' => $isActive,
                    'stage' => $status,
                    'stage_label' => $stageLabels[$status] ?? $status,
                    'started_at' => $run->started_at?->toISOString(),
                    'finished_at' => $run->finished_at?->toISOString(),
                    'created_at' => $run->created_at?->toISOString(),
                    'error_message' => $run->error_message,
                    'retryable' => $status === 'failed' && $lo
                        && ($objectType === 'quiz' ? !$lo->quiz : !$lo->latestVersion),
                    'target_url' => $course
                        ? ($status === 'completed' && $lo
                            ? "/lecturer/courses/{$course->id}/{$editorPaths[$objectType]}/{$lo->id}"
                            : "/lecturer/courses/{$course->id}/learning-objects?type={$objectType}")
                        : null,
                ];
            });

        $tasks = $docRuns->concat($generationRuns)->sortByDesc(fn ($t) => $t['created_at'])->values()->take(25);
        $activeCount = $tasks->filter(fn ($t) => $t['is_active'])->count();

        return response()->json([
            'tasks' => $tasks,
            'active_count' => $activeCount,
        ]);
    }
}
