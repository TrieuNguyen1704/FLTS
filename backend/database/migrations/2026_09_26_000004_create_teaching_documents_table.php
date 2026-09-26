<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teaching_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            // Only the pending state is written in Sprint 1; other values reserve the future pipeline contract.
            $table->enum('processing_status', ['uploaded_pending_processing', 'processing', 'processed', 'failed'])
                ->default('uploaded_pending_processing');
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('teaching_documents'); }
};
