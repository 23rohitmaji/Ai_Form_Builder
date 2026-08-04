<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormBuilderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_publish_and_collect_form_submissions(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->assertCreated()->json('token');

        $formId = $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Workshop Registration',
            'slug' => 'workshop-registration',
            'description' => 'Collect registrations for an AI workshop.',
            'is_published' => true,
            'fields' => [
                ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'is_required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
                ['key' => 'attendance_mode', 'label' => 'Attendance mode', 'type' => 'select', 'is_required' => true, 'options' => ['Online', 'In person']],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'workshop-registration')
            ->assertJsonCount(3, 'data.fields')
            ->json('data.id');

        $this->getJson('/api/public/forms/workshop-registration')
            ->assertOk()
            ->assertJsonPath('data.title', 'Workshop Registration');

        $this->postJson('/api/public/forms/workshop-registration/submissions', [
            'answers' => [
                'full_name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'attendance_mode' => 'Online',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'processed');

        $this->withToken($token)->getJson("/api/forms/$formId/analytics")
            ->assertOk()
            ->assertJsonPath('total_submissions', 1)
            ->assertJsonPath('processed_submissions', 1);
    }

    public function test_submission_rejects_unknown_and_invalid_dynamic_fields(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->json('token');

        $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Feedback',
            'slug' => 'feedback',
            'is_published' => true,
            'fields' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
                ['key' => 'rating', 'label' => 'Rating', 'type' => 'number', 'is_required' => true, 'validation_rules' => ['min:1', 'max:5']],
            ],
        ])->assertCreated();

        $this->postJson('/api/public/forms/feedback/submissions', [
            'answers' => [
                'email' => 'not-an-email',
                'rating' => 7,
                'extra' => 'blocked',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers.email', 'answers.rating', 'answers.extra']);
    }

    public function test_select_options_can_contain_commas(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->json('token');

        $formId = $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Meal Preference',
            'slug' => 'meal-preference',
            'is_published' => true,
            'fields' => [
                [
                    'key' => 'meal',
                    'label' => 'Meal preference',
                    'type' => 'select',
                    'is_required' => true,
                    'options' => ['Vegetarian', 'No onion, no garlic', 'Gluten free'],
                ],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/public/forms/meal-preference/submissions', [
            'answers' => [
                'meal' => 'No onion, no garlic',
            ],
        ])->assertCreated();

        $export = $this->withToken($token)->get("/api/forms/$formId/submissions/export");

        $export->assertOk();
        ob_start();
        $export->baseResponse->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Meal preference', $csv);
        $this->assertStringContainsString('No onion, no garlic', $csv);
        $this->assertStringNotContainsString('status', $csv);
        $this->assertStringNotContainsString('answers', $csv);
    }

    public function test_rich_field_config_search_and_store_toggle_work(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->json('token');

        $formId = $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Scholarship Application',
            'slug' => 'scholarship-application',
            'is_published' => true,
            'store_submissions' => true,
            'fields' => [
                ['key' => 'intro', 'label' => 'Applicant details', 'type' => 'section', 'help_text' => 'Tell us about yourself.'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'placeholder' => '+91 99999 99999', 'help_text' => 'Use a reachable number.', 'is_required' => true],
                ['key' => 'portfolio', 'label' => 'Portfolio URL', 'type' => 'url', 'is_required' => true],
                ['key' => 'priority', 'label' => 'Review priority', 'type' => 'dropdown', 'is_required' => true, 'options' => ['Low', 'High, urgent']],
                ['key' => 'rating', 'label' => 'Need rating', 'type' => 'rating', 'is_required' => true, 'validation_rules' => ['min:1', 'max:5']],
                ['key' => 'document', 'label' => 'Document', 'type' => 'file', 'is_required' => true, 'validation_rules' => ['file_types:pdf,jpg', 'file_max:2048']],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.store_submissions', true)
            ->assertJsonPath('data.fields.1.placeholder', '+91 99999 99999')
            ->json('data.id');

        $this->postJson('/api/public/forms/scholarship-application/submissions', [
            'answers' => [
                'phone' => '+91 99999 99999',
                'portfolio' => 'https://example.com',
                'priority' => 'High, urgent',
                'rating' => 5,
                'document' => [['name' => 'marksheet.pdf', 'size' => 1024 * 300, 'type' => 'application/pdf']],
            ],
        ])->assertCreated();

        $this->withToken($token)->getJson("/api/forms/$formId/submissions?search=urgent")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/public/forms/scholarship-application/submissions', [
            'answers' => [
                'phone' => '+91 99999 99999',
                'portfolio' => 'https://example.com',
                'priority' => 'High, urgent',
                'rating' => 5,
                'document' => [['name' => 'script.exe', 'size' => 1024 * 300, 'type' => 'application/octet-stream']],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['answers.document.0']);

        $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Privacy Form',
            'slug' => 'privacy-form',
            'is_published' => true,
            'store_submissions' => false,
            'fields' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
            ],
        ])->assertCreated();

        $this->postJson('/api/public/forms/privacy-form/submissions', [
            'answers' => ['email' => 'ada@example.com'],
        ])->assertAccepted()
            ->assertJsonPath('data.status', 'not_stored');
    }

    public function test_ai_endpoint_returns_a_usable_form_schema_without_external_credentials(): void
    {
        config()->set('services.groq.api_key', null);
        config()->set('services.openai.api_key', null);

        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->json('token');

        $this->withToken($token)->postJson('/api/ai/forms', [
            'prompt' => 'Create an event registration form for an AI workshop.',
        ])
            ->assertOk()
            ->assertJsonPath('schema.is_published', true)
            ->assertJsonPath('schema.fields.0.key', 'full_name')
            ->assertJsonPath('schema.fields.2.key', 'preferred_date');
    }

    public function test_submissions_are_paginated_five_per_page_by_default(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->json('token');

        $formId = $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Contact Form',
            'slug' => 'contact-form',
            'is_published' => true,
            'fields' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
            ],
        ])->assertCreated()->json('data.id');

        foreach (range(1, 7) as $index) {
            $this->postJson('/api/public/forms/contact-form/submissions', [
                'answers' => ['email' => "person$index@example.com"],
            ])->assertCreated();
        }

        $this->withToken($token)->getJson("/api/forms/$formId/submissions")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_ai_endpoint_can_edit_an_existing_schema_without_external_credentials(): void
    {
        config()->set('services.groq.api_key', null);
        config()->set('services.openai.api_key', null);

        $token = $this->postJson('/api/register', [
            'name' => 'Rohit',
            'email' => 'rohit@example.com',
            'password' => 'password123',
        ])->json('token');

        $schema = [
            'title' => 'Employee Form',
            'description' => 'Employee details.',
            'fields' => [
                ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'is_required' => true],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'is_required' => false],
            ],
        ];

        $edited = $this->withToken($token)->postJson('/api/ai/forms', [
            'prompt' => 'Add an emergency contact section and make phone required.',
            'schema' => $schema,
        ])
            ->assertOk()
            ->assertJsonPath('schema.fields.1.is_required', true)
            ->json('schema');

        $this->assertContains('emergency_contact_phone', collect($edited['fields'])->pluck('key')->all());

        $this->withToken($token)->postJson('/api/ai/forms', [
            'prompt' => 'Translate labels to Hindi.',
            'schema' => $schema,
        ])
            ->assertOk()
            ->assertJsonPath('schema.fields.0.label', 'पूरा नाम');
    }
}
