<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('quiz_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->unique(['quiz_attempt_id', 'quiz_question_id'], 'quiz_attempt_question_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('quiz_attempt_answers'); }
};
