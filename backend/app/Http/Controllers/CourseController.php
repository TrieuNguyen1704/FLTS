<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCourseRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CourseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role === 'lecturer') {
            $courses = Course::with('lecturer:id,name,email')->where('lecturer_id', $user->id)->latest()->get();
        } elseif ($user->role === 'student') {
            $courses = Course::with('lecturer:id,name,email')->whereHas('students', fn ($q) => $q->where('users.id', $user->id))->latest()->get();
        } else {
            $courses = Course::with('lecturer:id,name,email')->latest()->get();
        }
        return response()->json(['courses' => $courses]);
    }

    public function store(SaveCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $course = $request->user()->courses()->create($data);
        return response()->json(['course' => $course], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $this->ensureCanAccess($request, $course);
        return response()->json(['course' => $course->load('lecturer:id,name,email')]);
    }

    public function update(SaveCourseRequest $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validated();
        $course->update($data);
        return response()->json(['course' => $course]);
    }

    public function students(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $students = $course->students()
            ->select('users.id', 'users.name', 'users.email', 'users.account_status', 'course_enrollments.created_at as enrolled_at')
            ->withCount(['quizAttempts as attempts_count' => function ($query) use ($course) {
                $query->whereHas('quiz.learningObject', function ($q) use ($course) {
                    $q->where('course_id', $course->id);
                });
            }])
            ->orderBy('course_enrollments.created_at', 'desc')
            ->get();

        return response()->json(['students' => $students]);
    }

    public function availableStudents(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $search = trim((string) $request->query('q', ''));

        $query = User::query()
            ->where('role', 'student')
            ->where('account_status', 'active')
            ->whereNotIn('id', $course->students()->select('users.id'));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $students = $query->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['students' => $students]);
    }

    public function enroll(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:users,id']]);
        $student = User::findOrFail($data['student_id']);
        if ($student->role !== 'student') {
            return response()->json(['message' => 'Only Student accounts can be enrolled.'], 422);
        }
        if ($student->account_status !== 'active') {
            return response()->json(['message' => 'Chỉ có thể ghi danh tài khoản sinh viên đang hoạt động.'], 422);
        }
        $course->students()->syncWithoutDetaching([$student->id]);
        return response()->json([
            'message' => 'Student granted course access.',
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'account_status' => $student->account_status,
            ],
        ]);
    }

    public function unenroll(Request $request, Course $course, User $student): JsonResponse
    {
        $this->ensureOwner($request, $course);
        if (!$course->students()->where('users.id', $student->id)->exists()) {
            return response()->json(['message' => 'Sinh viên không có trong khóa học này.'], 404);
        }
        $course->students()->detach($student->id);
        return response()->json(['message' => 'Đã hủy ghi danh sinh viên khỏi khóa học.']);
    }

    public function join(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'student', 403, 'Chỉ tài khoản sinh viên mới có thể tham gia khóa học.');
        if ($user->account_status !== 'active') {
            return response()->json(['message' => 'Tài khoản của bạn đang bị tạm khóa.'], 422);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'enrollment_code' => ['nullable', 'string', 'max:32'],
        ]);
        $inputCode = trim($data['code'] ?? $data['enrollment_code'] ?? '');
        if (empty($inputCode)) {
            return response()->json(['message' => 'Vui lòng cung cấp mã ghi danh.'], 422);
        }

        $course = Course::where('enrollment_code', $inputCode)->first();
        if (!$course) {
            return response()->json(['message' => 'Mã tham gia không hợp lệ hoặc không tồn tại.'], 404);
        }

        if (!$course->is_enrollment_open) {
            return response()->json(['message' => 'Khóa học này hiện đã đóng nhận sinh viên mới.'], 422);
        }

        if ($course->students()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Bạn đã tham gia khóa học này từ trước.',
                'course' => $course->load('lecturer:id,name,email'),
            ]);
        }

        $course->students()->attach($user->id);

        return response()->json([
            'message' => 'Tham gia khóa học thành công!',
            'course' => $course->load('lecturer:id,name,email'),
        ]);
    }

    public function regenerateEnrollmentCode(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $newCode = 'FLTS-' . strtoupper(Str::random(6));
        $course->update(['enrollment_code' => $newCode]);

        return response()->json([
            'message' => 'Đã tạo mã ghi danh mới.',
            'enrollment_code' => $newCode,
        ]);
    }

    public function toggleEnrollment(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $isOpen = !$course->is_enrollment_open;
        $course->update(['is_enrollment_open' => $isOpen]);

        return response()->json([
            'message' => $isOpen ? 'Đã mở ghi danh khóa học.' : 'Đã khóa ghi danh khóa học.',
            'is_enrollment_open' => $isOpen,
        ]);
    }

    public function destroy(Request $request, Course $course, RagService $rag): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate(['confirmation' => ['required', 'string']]);
        if (!hash_equals($course->code, $data['confirmation'])) {
            return response()->json(['message' => 'Mã xác nhận không khớp với mã khóa học.'], 422);
        }

        // Deleting while a worker can still write results would leave files or vectors without their parent course.
        if ($course->documents()->where('processing_status', 'processing')->exists()
            || $course->learningObjects()->whereHas('generationRuns', fn ($query) => $query->whereIn('status', ['queued', 'generating']))->exists()) {
            return response()->json(['message' => 'Khóa học đang có tác vụ xử lý. Vui lòng chờ tác vụ kết thúc trước khi xóa.'], 409);
        }

        $documents = $course->documents()->get(['stored_path', 'processing_status']);
        if ($documents->whereIn('processing_status', ['processed', 'failed'])->isNotEmpty()) {
            try {
                // External vector cleanup happens before destructive database changes; failure leaves the course intact.
                $rag->deleteCourseVectors($course);
            } catch (Throwable $exception) {
                report($exception);
                return response()->json(['message' => 'Chưa thể xóa khóa học vì kho tìm kiếm đang không khả dụng. Dữ liệu chưa bị thay đổi.'], 503);
            }
        }

        $paths = $documents->pluck('stored_path')->filter()->values()->all();
        if ($paths !== [] && !Storage::disk('local')->delete($paths)) {
            return response()->json(['message' => 'Chưa thể xóa các tệp của khóa học. Dữ liệu khóa học chưa bị thay đổi.'], 503);
        }

        DB::transaction(fn () => $course->delete());
        return response()->json(['message' => 'Đã xóa khóa học và dữ liệu liên quan.']);
    }

    private function ensureOwner(Request $request, Course $course): void
    {
        // Route role middleware is not enough: a lecturer must not modify another lecturer's course.
        abort_unless($request->user()->role === 'lecturer' && $course->lecturer_id === $request->user()->id, 403);
    }

    private function ensureCanAccess(Request $request, Course $course): void
    {
        $user = $request->user();
        if ($user->role === 'admin' || ($user->role === 'lecturer' && $course->lecturer_id === $user->id)) {
            return;
        }
        // Student visibility is granted by the pivot table, not by a frontend-only filter.
        abort_unless($user->role === 'student' && $course->students()->where('users.id', $user->id)->exists(), 403);
    }
}
