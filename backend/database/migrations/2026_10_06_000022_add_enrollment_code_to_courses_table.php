<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('enrollment_code', 16)->nullable()->unique()->after('code');
            $table->boolean('is_enrollment_open')->default(true)->after('enrollment_code');
        });

        // Populate unique enrollment codes for existing courses
        $courses = DB::table('courses')->whereNull('enrollment_code')->get();
        foreach ($courses as $course) {
            $code = 'FLTS-' . strtoupper(Str::random(6));
            DB::table('courses')->where('id', $course->id)->update([
                'enrollment_code' => $code,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['enrollment_code', 'is_enrollment_open']);
        });
    }
};
