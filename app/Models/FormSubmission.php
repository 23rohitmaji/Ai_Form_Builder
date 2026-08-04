<?php

namespace App\Models;

use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'form_id',
        'answers',
        'status',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
