<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $course = $request->user()->courses()->create($data);
        return response()->json(['course' => $course], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $this->ensureCanAccess($request, $course);
        return response()->json(['course' => $course->load('lecturer:id,name,email')]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $course->update($data);
        return response()->json(['course' => $course]);
    }

    public function enroll(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:users,id']]);
        $student = User::findOrFail($data['student_id']);
        if ($student->role !== 'student') {
            return response()->json(['message' => 'Only Student accounts can be enrolled.'], 422);
        }
        $course->students()->syncWithoutDetaching([$student->id]);
        return response()->json(['message' => 'Student granted course access.']);
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
