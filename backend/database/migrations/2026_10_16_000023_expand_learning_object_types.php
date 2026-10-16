<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Keep one learning-object aggregate while version payloads carry type-specific content.
        Schema::table('learning_objects', function (Blueprint $table) {
            $table->enum('type', ['quiz', 'flashcard', 'mindmap', 'slide'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('learning_objects', function (Blueprint $table) {
            $table->enum('type', ['quiz', 'flashcard'])->change();
        });
    }
};
