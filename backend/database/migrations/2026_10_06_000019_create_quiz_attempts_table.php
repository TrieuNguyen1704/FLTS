<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->decimal('score', 5, 2)->default(0);
            $table->unsignedInteger('total_correct')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress')->index();
            $table->timestamps();
            $table->unique(['quiz_id', 'student_id', 'attempt_number'], 'quiz_student_attempt_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('quiz_attempts'); }
};
