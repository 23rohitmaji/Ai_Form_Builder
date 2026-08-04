<?php

namespace App\Models;

use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    public const TYPES = [
        'text',
        'textarea',
        'email',
        'phone',
        'url',
        'number',
        'date',
        'dropdown',
        'select',
        'radio',
        'checkbox',
        'boolean',
        'file',
        'section',
        'rating',
    ];

    protected $fillable = [
        'form_id',
        'key',
        'label',
        'placeholder',
        'help_text',
        'default_value',
        'type',
        'is_required',
        'options',
        'validation_rules',
        'section',
        'step',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'default_value' => 'array',
            'options' => 'array',
            'validation_rules' => 'array',
            'position' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
