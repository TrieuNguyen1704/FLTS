<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Stop before changing the schema; never rename or delete existing team data.
        if (DB::table('courses')->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate course codes exist. Resolve them before running this migration.');
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->unique('code', 'courses_code_unique');
            $table->index(['lecturer_id', 'created_at', 'id'], 'courses_owner_recent_index');
        });
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->index(['course_id', 'created_at', 'id'], 'documents_course_recent_index');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_documents', function (Blueprint $table) {
            $table->dropIndex('documents_course_recent_index');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_owner_recent_index');
            $table->dropUnique('courses_code_unique');
        });
    }
};
