<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleFormSeeder extends Seeder
{
    /**
     * Seed a reviewer account and representative form-builder data.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password123'),
            ],
        );

        $form = Form::query()->updateOrCreate(
            ['slug' => 'ai-workshop-registration'],
            [
                'user_id' => $user->id,
                'title' => 'AI Workshop Registration',
                'description' => 'Demo form for collecting workshop registrations.',
                'is_published' => true,
                'store_submissions' => true,
                'settings' => ['confirmation_message' => 'Thanks for registering.'],
            ],
        );

        $form->fields()->delete();
        $form->fields()->createMany([
            ['key' => 'intro', 'label' => 'Participant details', 'type' => 'section', 'help_text' => 'Basic contact information.', 'position' => 0],
            ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'is_required' => true, 'validation_rules' => ['min_length:2'], 'position' => 1],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true, 'position' => 2],
            ['key' => 'phone', 'label' => 'Phone number', 'type' => 'phone', 'placeholder' => '+91 99999 99999', 'is_required' => true, 'section' => 'Contact', 'position' => 3],
            ['key' => 'experience_years', 'label' => 'Years of experience', 'type' => 'number', 'validation_rules' => ['min:0', 'max:40'], 'position' => 4],
            ['key' => 'preferred_date', 'label' => 'Preferred date', 'type' => 'date', 'is_required' => true, 'step' => 'Schedule', 'position' => 5],
            ['key' => 'attendance_mode', 'label' => 'Attendance mode', 'type' => 'dropdown', 'is_required' => true, 'options' => ['Online', 'In person'], 'position' => 6],
            ['key' => 'skills', 'label' => 'Skills', 'type' => 'checkbox', 'options' => ['PHP', 'Laravel', 'React', 'AI integrations'], 'position' => 7],
            ['key' => 'portfolio', 'label' => 'Portfolio URL', 'type' => 'url', 'validation_rules' => ['url'], 'position' => 8],
            ['key' => 'resume', 'label' => 'Resume', 'type' => 'file', 'validation_rules' => ['file_types:pdf,doc,docx', 'file_max:2048'], 'help_text' => 'Upload metadata is stored for demo purposes.', 'position' => 9],
            ['key' => 'expectations', 'label' => 'Expectations', 'type' => 'textarea', 'placeholder' => 'What do you want to learn?', 'position' => 10],
            ['key' => 'interest_rating', 'label' => 'Interest rating', 'type' => 'rating', 'default_value' => ['value' => 4], 'position' => 11],
        ]);

        $form->submissions()->delete();
        $form->submissions()->create([
            'answers' => [
                'full_name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'phone' => '+91 99999 99999',
                'experience_years' => 3,
                'preferred_date' => '2026-08-20',
                'attendance_mode' => 'Online',
                'skills' => ['Laravel', 'React'],
                'portfolio' => 'https://example.com/ada',
                'resume' => [['name' => 'ada-resume.pdf', 'size' => 524288, 'type' => 'application/pdf']],
                'expectations' => 'Hands-on AI workflow automation.',
                'interest_rating' => 5,
            ],
            'status' => 'processed',
            'processed_at' => now(),
            'metadata' => ['seeded' => true],
        ]);
    }
}
