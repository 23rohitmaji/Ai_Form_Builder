<?php

namespace App\Http\Requests;

use App\Models\FormField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash', 'max:180', 'unique:forms,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'boolean'],
            'store_submissions' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'fields' => ['required', 'array', 'min:1', 'max:80'],
            'fields.*.key' => ['required', 'alpha_dash', 'max:80', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:160'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'fields.*.default_value' => ['nullable'],
            'fields.*.type' => ['required', Rule::in(FormField::TYPES)],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:255'],
            'fields.*.validation_rules' => ['nullable', 'array'],
            'fields.*.validation_rules.*' => ['string', 'max:255'],
            'fields.*.section' => ['nullable', 'string', 'max:120'],
            'fields.*.step' => ['nullable', 'string', 'max:120'],
        ];
    }
}
