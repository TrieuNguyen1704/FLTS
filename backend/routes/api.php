<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentProcessingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'laravel-api']));
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/password-reset/request', [AuthController::class, 'requestPasswordReset']);
Route::post('/auth/password-reset', [AuthController::class, 'resetPassword']);

// Every product route below resolves the bearer token before a controller can trust $request->user().
Route::middleware('auth.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/admin/users', [AdminUserController::class, 'index'])->middleware('role:admin');
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->middleware('role:admin');
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::post('/courses', [CourseController::class, 'store'])->middleware('role:lecturer');
    Route::patch('/courses/{course}', [CourseController::class, 'update'])->middleware('role:lecturer');
    Route::post('/courses/{course}/enrollments', [CourseController::class, 'enroll'])->middleware('role:lecturer');
    // Documents remain lecturer-only in Sprint 1; student delivery is not implemented yet.
    Route::get('/courses/{course}/documents', [DocumentController::class, 'index'])->middleware('role:lecturer');
    Route::post('/courses/{course}/documents', [DocumentController::class, 'store'])->middleware('role:lecturer');
    Route::get('/courses/{course}/documents/{document}/download', [DocumentController::class, 'download'])->middleware('role:lecturer');
    Route::delete('/courses/{course}/documents/{document}', [DocumentController::class, 'destroy'])->middleware('role:lecturer');
    // Sprint 2 RAG calls are lecturer-owned course operations; FastAPI is never exposed directly to browsers.
    Route::post('/courses/{course}/documents/{document}/processing-runs', [DocumentProcessingController::class, 'start'])->middleware('role:lecturer');
    Route::post('/courses/{course}/documents/{document}/processing-runs/retry', [DocumentProcessingController::class, 'retry'])->middleware('role:lecturer');
    Route::get('/courses/{course}/documents/{document}/processing', [DocumentProcessingController::class, 'show'])->middleware('role:lecturer');
    Route::post('/courses/{course}/retrieval-tests', [DocumentProcessingController::class, 'search'])->middleware('role:lecturer');
    Route::post('/courses/{course}/evidence-prototypes', [DocumentProcessingController::class, 'generateEvidence'])->middleware('role:lecturer');
});
