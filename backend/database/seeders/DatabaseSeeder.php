<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate keeps container restarts from creating duplicate demo identities.
        $lecturer = User::firstOrCreate(['email' => 'lecturer@flts.test'], [
            'name' => 'Demo Lecturer', 'password' => Hash::make('DemoPass123!'), 'role' => 'lecturer',
        ]);
        $student = User::firstOrCreate(['email' => 'student@flts.test'], [
            'name' => 'Demo Student', 'password' => Hash::make('DemoPass123!'), 'role' => 'student',
        ]);
        User::firstOrCreate(['email' => 'admin@flts.test'], [
            'name' => 'Demo Administrator', 'password' => Hash::make('DemoPass123!'), 'role' => 'admin',
        ]);
        $course = Course::firstOrCreate(['lecturer_id' => $lecturer->id, 'code' => 'FLIP-101'], [
            'name' => 'Flipped Learning Foundations',
            'description' => 'Demo course seeded for Sprint 1. Document processing is not implemented.',
        ]);
        // The seeded enrollment is the concrete Student-access example used by the demo.
        $course->students()->syncWithoutDetaching([$student->id]);
    }
}
