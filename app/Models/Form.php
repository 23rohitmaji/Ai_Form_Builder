<?php

namespace App\Models;

use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'is_published',
        'store_submissions',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'store_submissions' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
