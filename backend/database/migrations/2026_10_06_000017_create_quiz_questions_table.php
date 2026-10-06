<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_index');
            $table->text('question_text');
            $table->enum('question_type', ['single_choice'])->default('single_choice');
            $table->text('explanation')->nullable();
            $table->json('citations')->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'question_index']);
        });
    }

    public function down(): void { Schema::dropIfExists('quiz_questions'); }
};
