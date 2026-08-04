<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormSchemaService
{
    public function syncFields(Form $form, array $fields): void
    {
        $form->fields()->delete();

        foreach (array_values($fields) as $position => $field) {
            $form->fields()->create([
                'key' => $field['key'],
                'label' => $field['label'],
                'placeholder' => $field['placeholder'] ?? null,
                'help_text' => $field['help_text'] ?? null,
                'default_value' => $this->wrapDefaultValue($field['default_value'] ?? null),
                'type' => $field['type'],
                'is_required' => (bool) ($field['is_required'] ?? false),
                'options' => $field['options'] ?? null,
                'validation_rules' => $field['validation_rules'] ?? null,
                'section' => $field['section'] ?? null,
                'step' => $field['step'] ?? null,
                'position' => $position,
            ]);
        }
    }

    public function validateAnswers(Form $form, array $answers): array
    {
        $rules = [];
        $inputFields = $form->fields->reject(fn (FormField $field) => $field->type === 'section');
        $allowedKeys = $inputFields->pluck('key')->all();

        foreach ($inputFields as $field) {
            $rules["answers.{$field->key}"] = $this->rulesForField($field);
        }

        $validator = Validator::make(['answers' => $answers], $rules);

        $validator->after(function ($validator) use ($answers, $allowedKeys, $inputFields) {
            foreach (array_keys($answers) as $key) {
                if (! in_array($key, $allowedKeys, true)) {
                    $validator->errors()->add("answers.$key", 'This field is not part of the form schema.');
                }
            }

            foreach ($inputFields->where('type', 'file') as $field) {
                $this->validateFileMetadata($validator, $field, $answers[$field->key] ?? []);
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return array_intersect_key($answers, array_flip($allowedKeys));
    }

    private function rulesForField(FormField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        $rules[] = match ($field->type) {
            'email' => 'email',
            'number', 'rating' => 'numeric',
            'date' => 'date',
            'checkbox' => 'array',
            'boolean' => 'boolean',
            'url' => 'url',
            'file' => 'array',
            default => 'string',
        };

        if (in_array($field->type, ['select', 'dropdown', 'radio'], true) && $field->options) {
            $rules[] = Rule::in($field->options);
        }

        if ($field->type === 'phone') {
            $rules[] = 'regex:/^[0-9+().\\-\\s]{7,20}$/';
        }

        $rules = [...$rules, ...$this->normalizedValidationRules($field)];

        return $rules;
    }

    private function normalizedValidationRules(FormField $field): array
    {
        return collect($field->validation_rules ?? [])
            ->filter(fn ($rule) => is_string($rule))
            ->map(fn (string $rule) => trim($rule))
            ->filter()
            ->map(function (string $rule) use ($field) {
                if ($rule === 'length' || $rule === 'numeric') {
                    return $field->type === 'number' || $field->type === 'rating' ? 'numeric' : null;
                }

                if (in_array($rule, ['email', 'url'], true)) {
                    return $rule;
                }

                if (str_starts_with($rule, 'min_length:')) {
                    return 'min:'.str($rule)->after(':')->toString();
                }

                if (str_starts_with($rule, 'max_length:')) {
                    return 'max:'.str($rule)->after(':')->toString();
                }

                if (str_starts_with($rule, 'min:') || str_starts_with($rule, 'max:') || str_starts_with($rule, 'regex:')) {
                    return $rule;
                }

                if (str_starts_with($rule, 'file_types:') || str_starts_with($rule, 'file_max:')) {
                    return null;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function wrapDefaultValue(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ['value' => $value];
    }

    private function validateFileMetadata($validator, FormField $field, mixed $files): void
    {
        if (! is_array($files)) {
            return;
        }

        $allowedTypes = null;
        $maxKilobytes = null;

        foreach ($field->validation_rules ?? [] as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            if (str_starts_with($rule, 'file_types:')) {
                $allowedTypes = collect(explode(',', str($rule)->after(':')->toString()))
                    ->map(fn ($type) => trim(strtolower($type)))
                    ->filter()
                    ->all();
            }

            if (str_starts_with($rule, 'file_max:')) {
                $maxKilobytes = (int) str($rule)->after(':')->toString();
            }
        }

        foreach ($files as $index => $file) {
            if (! is_array($file)) {
                continue;
            }

            $name = strtolower((string) ($file['name'] ?? ''));
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $sizeKilobytes = (int) ceil(((int) ($file['size'] ?? 0)) / 1024);

            if ($allowedTypes && ! in_array($extension, $allowedTypes, true)) {
                $validator->errors()->add("answers.$field->key.$index", 'The file type is not allowed.');
            }

            if ($maxKilobytes && $sizeKilobytes > $maxKilobytes) {
                $validator->errors()->add("answers.$field->key.$index", 'The file exceeds the maximum size.');
            }
        }
    }
}
