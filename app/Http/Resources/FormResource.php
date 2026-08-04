<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'store_submissions' => $this->store_submissions,
            'settings' => $this->settings,
            'fields' => $this->whenLoaded('fields', fn () => $this->fields->map(fn ($field) => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'help_text' => $field->help_text,
                'default_value' => $field->default_value,
                'type' => $field->type,
                'is_required' => $field->is_required,
                'options' => $field->options,
                'validation_rules' => $field->validation_rules,
                'section' => $field->section,
                'step' => $field->step,
                'position' => $field->position,
            ])),
            'submissions_count' => $this->whenCounted('submissions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
