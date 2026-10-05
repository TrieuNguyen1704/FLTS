<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_vector_references', function (Blueprint $table) {
            // Store the exact embedding model beside every vector so re-embedding remains auditable.
            $table->string('embedding_model', 120)->default('text-embedding-004')->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('document_vector_references', function (Blueprint $table) {
            $table->dropColumn('embedding_model');
        });
    }
};
