<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\TeachingDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $maxKb = (int) env('DOCUMENT_MAX_KB', 10240);
        $request->validate(['document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.$maxKb]]);
        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension());
        // A UUID prevents colliding or user-controlled storage paths while preserving the original name as metadata.
        $storedPath = $file->storeAs('documents/'.$course->id, Str::uuid().'.'.$extension, 'local');

        $document = TeachingDocument::create([
            'course_id' => $course->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            // Sprint 1 only persists the input. No extraction or RAG worker is connected yet.
            'processing_status' => 'uploaded_pending_processing',
        ]);

        return response()->json(['document' => $document], 201);
    }

    public function download(Request $request, Course $course, TeachingDocument $document): BinaryFileResponse
    {
        $this->ensureOwner($request, $course);
        abort_unless($document->course_id === $course->id, 404);
        return Storage::disk('local')->download($document->stored_path, $document->original_name);
    }

    public function destroy(Request $request, Course $course, TeachingDocument $document): JsonResponse
    {
        $this->ensureOwner($request, $course);
        abort_unless($document->course_id === $course->id, 404);
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
