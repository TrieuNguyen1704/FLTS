<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentRequest;
use App\Models\Course;
use App\Models\TeachingDocument;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DocumentController
{
    public function index(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim((string) ($data['q'] ?? ''));
        return response()->json(['documents' => $course->documents()
            ->when($query !== '', fn ($documents) => $documents->where('original_name', 'like', "%{$query}%"))
            ->latest()
            ->get()]);
    }

    public function store(UploadDocumentRequest $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension());
        // A UUID prevents colliding or user-controlled storage paths while preserving the original name as metadata.
        $storedPath = $file->storeAs('documents/'.$course->id, Str::uuid().'.'.$extension, 'local');
        if ($storedPath === false) {
            throw new HttpException(503, 'Không thể lưu tài liệu. Vui lòng thử lại.');
        }

        try {
            $document = TeachingDocument::create([
                'course_id' => $course->id,
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'extension' => $extension,
                'size_bytes' => $file->getSize(),
                // Upload only stores source + metadata; the Lecturer explicitly starts the separate RAG queue run.
                'processing_status' => 'uploaded_pending_processing',
            ]);
        } catch (Throwable $exception) {
            // Database failure must not leave an untracked upload on the HDD.
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        return response()->json(['document' => $document], 201);
    }

    public function download(Request $request, Course $course, TeachingDocument $document): BinaryFileResponse
    {
        $this->ensureOwner($request, $course);
        abort_unless($document->course_id === $course->id, 404);
        return Storage::disk('local')->download($document->stored_path, $document->original_name);
    }

    public function destroy(Request $request, Course $course, TeachingDocument $document, RagService $rag): JsonResponse
    {
        $this->ensureOwner($request, $course);
        abort_unless($document->course_id === $course->id, 404);

        // Removing the source while its queued job can still upsert vectors creates an unrecoverable race.
        if ($document->processing_status === 'processing') {
            return response()->json(['message' => 'This document is currently being processed and cannot be deleted yet.'], 409);
        }

        if (in_array($document->processing_status, ['processed', 'failed'], true)) {
            try {
                // Failed persistence can leave vectors behind; pending uploads cannot have been indexed yet.
                $rag->deleteDocumentVectors($document);
            } catch (\RuntimeException $exception) {
                return response()->json(['message' => 'Document deletion is paused because vector cleanup is unavailable.'], 503);
            }
        }
        Storage::disk('local')->delete($document->stored_path);
        $document->delete();
        return response()->json(['message' => 'Document deleted.']);
    }

    private function ensureOwner(Request $request, Course $course): void
    {
        // File endpoints repeat ownership validation because route parameters alone do not authorize a resource.
        abort_unless($request->user()->role === 'lecturer' && $course->lecturer_id === $request->user()->id, 403);
    }
}
