<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            // Keep the latest run on the document for fast UI polling while preserving every attempt below.
            $table->foreignId('latest_processing_run_id')->nullable()->after('processing_error')
                ->constrained('document_processing_runs')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('latest_processing_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('latest_processing_run_id');
            $table->dropColumn('processed_at');
        });
    }
};
