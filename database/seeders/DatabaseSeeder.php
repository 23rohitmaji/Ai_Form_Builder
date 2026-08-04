<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $form = $user->forms()->create([
            'title' => 'AI Workshop Registration',
            'slug' => 'ai-workshop-registration',
            'description' => 'Demo form for collecting workshop registrations.',
            'is_published' => true,
            'settings' => ['confirmation_message' => 'Thanks for registering.'],
        ]);

        $form->fields()->createMany([
            ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'is_required' => true, 'position' => 0],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true, 'position' => 1],
            ['key' => 'attendance_mode', 'label' => 'Attendance mode', 'type' => 'select', 'is_required' => true, 'options' => ['Online', 'In person'], 'position' => 2],
        ]);

        $form->submissions()->create([
            'answers' => [
                'full_name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'attendance_mode' => 'Online',
            ],
            'status' => 'processed',
            'processed_at' => now(),
            'metadata' => ['seeded' => true],
        ]);
    }
}
