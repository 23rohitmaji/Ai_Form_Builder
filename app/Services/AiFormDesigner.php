<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiFormDesigner
{
    private const SUPPORTED_TYPES = ['text', 'textarea', 'email', 'phone', 'url', 'number', 'date', 'dropdown', 'select', 'radio', 'checkbox', 'boolean', 'file', 'section', 'rating'];

    public function generate(string $prompt, ?string $title = null): array
    {
        if (config('services.llm.provider') === 'groq' && config('services.groq.api_key')) {
            $generated = $this->generateWithGroq($prompt, $title);

            if ($generated !== null) {
                return $generated;
            }
        }

        if (config('services.llm.provider') === 'openai' && config('services.openai.api_key')) {
            $generated = $this->generateWithOpenAi($prompt, $title);

            if ($generated !== null) {
                return $generated;
            }
        }

        return $this->generateHeuristicSchema($prompt, $title);
    }

    public function edit(array $schema, string $instruction): array
    {
        if (config('services.llm.provider') === 'groq' && config('services.groq.api_key')) {
            $generated = $this->editWithGroq($schema, $instruction);

            if ($generated !== null) {
                return $generated;
            }
        }

        if (config('services.llm.provider') === 'openai' && config('services.openai.api_key')) {
            $generated = $this->editWithOpenAi($schema, $instruction);

            if ($generated !== null) {
                return $generated;
            }
        }

        return $this->editHeuristicSchema($schema, $instruction);
    }

    private function generateWithGroq(string $prompt, ?string $title): ?array
    {
        $response = Http::withToken(config('services.groq.api_key'))
            ->timeout(20)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model', 'llama-3.3-70b-versatile'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->schemaInstruction(),
                    ],
                    [
                        'role' => 'user',
                        'content' => trim(($title ? "Title: $title\n" : '').$prompt),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $text = data_get($response->json(), 'choices.0.message.content');
        $schema = is_string($text) ? json_decode($text, true) : null;

        return is_array($schema) ? $this->normalize($schema, $prompt, $title, 'groq') : null;
    }

    private function editWithGroq(array $schema, string $instruction): ?array
    {
        $response = Http::withToken(config('services.groq.api_key'))
            ->timeout(20)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model', 'llama-3.3-70b-versatile'),
                'temperature' => 0.15,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->editInstruction()],
                    ['role' => 'user', 'content' => json_encode([
                        'instruction' => $instruction,
                        'current_schema' => $schema,
                    ])],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $text = data_get($response->json(), 'choices.0.message.content');
        $edited = is_string($text) ? json_decode($text, true) : null;

        return is_array($edited) ? $this->normalize($edited, $instruction, $schema['title'] ?? null, 'groq') : null;
    }

    private function generateWithOpenAi(string $prompt, ?string $title): ?array
    {
        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(20)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5-mini'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $this->schemaInstruction(),
                    ],
                    ['role' => 'user', 'content' => trim(($title ? "Title: $title\n" : '').$prompt)],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $content = collect(data_get($payload, 'output', []))
            ->filter(fn ($item) => is_array($item))
            ->flatMap(fn (array $item) => $item['content'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->first(fn (array $item) => isset($item['text']));
        $text = data_get($payload, 'output_text') ?? ($content['text'] ?? null);

        $schema = is_string($text) ? json_decode($text, true) : null;

        return is_array($schema) ? $this->normalize($schema, $prompt, $title, 'openai') : null;
    }

    private function editWithOpenAi(array $schema, string $instruction): ?array
    {
        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(20)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5-mini'),
                'input' => [
                    ['role' => 'system', 'content' => $this->editInstruction()],
                    ['role' => 'user', 'content' => json_encode([
                        'instruction' => $instruction,
                        'current_schema' => $schema,
                    ])],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $content = collect(data_get($payload, 'output', []))
            ->filter(fn ($item) => is_array($item))
            ->flatMap(fn (array $item) => $item['content'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->first(fn (array $item) => isset($item['text']));
        $text = data_get($payload, 'output_text') ?? ($content['text'] ?? null);
        $edited = is_string($text) ? json_decode($text, true) : null;

        return is_array($edited) ? $this->normalize($edited, $instruction, $schema['title'] ?? null, 'openai') : null;
    }

    private function generateHeuristicSchema(string $prompt, ?string $title): array
    {
        $lower = Str::lower($prompt);
        $fields = [
            ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'is_required' => true],
            ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'is_required' => true],
        ];

        if (str_contains($lower, 'event') || str_contains($lower, 'workshop')) {
            $fields[] = ['key' => 'preferred_date', 'label' => 'Preferred date', 'type' => 'date', 'is_required' => true];
            $fields[] = ['key' => 'attendance_mode', 'label' => 'Attendance mode', 'type' => 'select', 'is_required' => true, 'options' => ['Online', 'In person', 'Hybrid']];
        }

        if (str_contains($lower, 'feedback') || str_contains($lower, 'survey')) {
            $fields[] = ['key' => 'rating', 'label' => 'Rating', 'type' => 'number', 'is_required' => true, 'validation_rules' => ['min:1', 'max:5']];
            $fields[] = ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea', 'is_required' => false];
        }

        if (count($fields) === 2) {
            $fields[] = ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'is_required' => true];
        }

        return $this->normalize([
            'title' => $title ?: Str::headline(Str::limit($prompt, 48, '')),
            'description' => 'Generated from prompt: '.Str::limit($prompt, 180),
            'fields' => $fields,
        ], $prompt, $title);
    }

    private function editHeuristicSchema(array $schema, string $instruction): array
    {
        $lower = Str::lower($instruction);
        $schema['fields'] = $schema['fields'] ?? [];

        if (str_contains($lower, 'emergency contact')) {
            $schema['fields'][] = ['key' => 'emergency_contact_section', 'label' => 'Emergency Contact', 'type' => 'section', 'help_text' => 'Person to contact in case of emergency.'];
            $schema['fields'][] = ['key' => 'emergency_contact_name', 'label' => 'Emergency contact name', 'type' => 'text', 'is_required' => true, 'section' => 'Emergency Contact'];
            $schema['fields'][] = ['key' => 'emergency_contact_phone', 'label' => 'Emergency contact phone', 'type' => 'phone', 'is_required' => true, 'section' => 'Emergency Contact'];
            $schema['fields'][] = ['key' => 'emergency_contact_relationship', 'label' => 'Relationship', 'type' => 'dropdown', 'is_required' => false, 'options' => ['Parent', 'Spouse', 'Sibling', 'Friend', 'Other'], 'section' => 'Emergency Contact'];
        }

        if (str_contains($lower, 'phone') && str_contains($lower, 'required')) {
            $schema['fields'] = collect($schema['fields'])->map(function (array $field) {
                if (str_contains((string) ($field['key'] ?? ''), 'phone') || str_contains(Str::lower((string) ($field['label'] ?? '')), 'phone')) {
                    $field['is_required'] = true;
                    $field['type'] = 'phone';
                }

                return $field;
            })->all();
        }

        if (str_contains($lower, 'hindi') || str_contains($lower, 'हिंदी')) {
            $schema['fields'] = collect($schema['fields'])->map(function (array $field) {
                $field['label'] = $this->hindiLabel((string) ($field['key'] ?? ''), (string) ($field['label'] ?? ''));

                return $field;
            })->all();
        }

        return $this->normalize($schema, $instruction, $schema['title'] ?? null, 'fallback');
    }

    private function normalize(array $schema, string $prompt, ?string $title, string $source = 'fallback'): array
    {
        $fields = collect($schema['fields'] ?? [])
            ->map(function (array $field, int $index) {
                $label = $field['label'] ?? $field['key'] ?? 'Field '.($index + 1);
                $type = $this->normalizeType((string) ($field['type'] ?? 'text'), $label);

                return [
                    'key' => Str::snake($field['key'] ?? $label),
                    'label' => Str::headline($label),
                    'placeholder' => $field['placeholder'] ?? null,
                    'help_text' => $field['help_text'] ?? null,
                    'default_value' => $field['default_value'] ?? null,
                    'type' => $type,
                    'is_required' => (bool) ($field['is_required'] ?? $field['required'] ?? false),
                    'options' => $this->normalizeOptions($field['options'] ?? null, $type),
                    'validation_rules' => $this->normalizeValidationRules($field['validation_rules'] ?? null, $type),
                    'section' => $field['section'] ?? null,
                    'step' => $field['step'] ?? null,
                ];
            })
            ->unique('key')
            ->values()
            ->all();

        return [
            'title' => $schema['title'] ?? $title ?? 'Generated Form',
            'description' => $schema['description'] ?? 'Generated from prompt: '.Str::limit($prompt, 180),
            'is_published' => $schema['is_published'] ?? true,
            'store_submissions' => true,
            'settings' => ['source' => 'ai', 'provider' => $source],
            'fields' => $fields,
        ];
    }

    private function schemaInstruction(): string
    {
        return 'Return only valid JSON for a Laravel form builder. Shape: {"title": string, "description": string, "store_submissions": boolean, "fields": [{"key": string, "label": string, "type": string, "placeholder": string|null, "help_text": string|null, "default_value": any|null, "is_required": boolean, "options": array|null, "validation_rules": array|null, "section": string|null, "step": string|null}]}. Supported field types: text,textarea,email,phone,url,number,date,dropdown,select,radio,checkbox,boolean,file,section,rating. Use concise snake_case keys. Add enough fields to satisfy the user prompt.';
    }

    private function editInstruction(): string
    {
        return $this->schemaInstruction().' You are editing an existing form. Preserve existing fields unless the instruction asks to change them. Return the complete edited schema, not a diff. Handle requests like adding sections, making a field required, changing field types, adding validation, and translating labels.';
    }

    private function hindiLabel(string $key, string $current): string
    {
        $dictionary = [
            'name' => 'नाम',
            'full_name' => 'पूरा नाम',
            'first_name' => 'पहला नाम',
            'last_name' => 'अंतिम नाम',
            'email' => 'ईमेल',
            'phone' => 'फोन नंबर',
            'phone_number' => 'फोन नंबर',
            'date' => 'तारीख',
            'address' => 'पता',
            'message' => 'संदेश',
            'comments' => 'टिप्पणियां',
            'rating' => 'रेटिंग',
            'emergency_contact_name' => 'आपातकालीन संपर्क का नाम',
            'emergency_contact_phone' => 'आपातकालीन संपर्क फोन',
            'emergency_contact_relationship' => 'संबंध',
        ];

        return $dictionary[$key] ?? $dictionary[Str::snake($current)] ?? $current;
    }

    private function normalizeType(string $type, string $label): string
    {
        $normalized = Str::of($type)->lower()->replace([' ', '-'], '_')->toString();
        $label = Str::lower($label);

        $mapped = match ($normalized) {
            'long_text', 'multi_line', 'multiline', 'paragraph', 'comments', 'comment' => 'textarea',
            'integer', 'decimal', 'float', 'currency', 'money', 'range', 'rating', 'gpa', 'percentage' => 'number',
            'datetime', 'time', 'month', 'year' => 'date',
            'choice', 'choices', 'single_select', 'multiselect', 'multi_select' => 'dropdown',
            'multiple_choice' => 'radio',
            'checklist', 'multi_checkbox', 'checkboxes' => 'checkbox',
            'yes_no', 'toggle', 'consent', 'agreement' => 'boolean',
            'tel' => 'phone',
            'website' => 'url',
            'upload', 'document', 'documents' => 'file',
            'heading', 'section_heading', 'divider' => 'section',
            'stars', 'star_rating' => 'rating',
            'address' => 'text',
            default => $normalized,
        };

        if (! in_array($mapped, self::SUPPORTED_TYPES, true)) {
            if (str_contains($label, 'email')) {
                return 'email';
            }

            if (str_contains($label, 'date')) {
                return 'date';
            }

            if (str_contains($label, 'income') || str_contains($label, 'priority') || str_contains($label, 'range')) {
                return 'dropdown';
            }

            if (str_contains($label, 'checklist') || str_contains($label, 'documents')) {
                return 'checkbox';
            }

            if (str_contains($label, 'consent') || str_contains($label, 'agree')) {
                return 'boolean';
            }

            return 'text';
        }

        return $mapped;
    }

    private function normalizeOptions(mixed $options, string $type): ?array
    {
        if (! in_array($type, ['select', 'dropdown', 'radio', 'checkbox'], true)) {
            return null;
        }

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', $options);
        }

        if (! is_array($options)) {
            return null;
        }

        $normalized = collect($options)
            ->map(fn ($option) => is_array($option) ? ($option['label'] ?? $option['value'] ?? null) : $option)
            ->filter(fn ($option) => is_string($option) || is_numeric($option))
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized ?: null;
    }

    private function normalizeValidationRules(mixed $rules, string $type): ?array
    {
        if (is_string($rules)) {
            $rules = preg_split('/\r\n|\r|\n|,/', $rules);
        }

        if (! is_array($rules)) {
            return null;
        }

        $allowedPrefixes = ['min:', 'max:', 'size:', 'regex:', 'min_length:', 'max_length:', 'file_types:', 'file_max:'];
        $allowedRules = ['email', 'numeric', 'date', 'boolean', 'string', 'array'];

        $normalized = collect($rules)
            ->filter(fn ($rule) => is_string($rule))
            ->map(fn (string $rule) => trim($rule))
            ->filter(function (string $rule) use ($allowedPrefixes, $allowedRules) {
                return in_array($rule, $allowedRules, true)
                    || collect($allowedPrefixes)->contains(fn (string $prefix) => str_starts_with($rule, $prefix));
            })
            ->reject(fn (string $rule) => in_array($rule, ['email', 'numeric', 'date', 'boolean', 'string', 'array'], true))
            ->values()
            ->all();

        return $normalized ?: null;
    }
}
