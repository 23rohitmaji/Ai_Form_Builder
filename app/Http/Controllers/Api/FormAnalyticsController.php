<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Support\Facades\Cache;

class FormAnalyticsController extends Controller
{
    public function __invoke(Form $form)
    {
        abort_unless($form->user_id === request()->user()->id, 403);

        return Cache::remember("forms.$form->id.analytics", 120, fn () => [
            'form_id' => $form->id,
            'total_submissions' => $form->submissions()->count(),
            'processed_submissions' => $form->submissions()->where('status', 'processed')->count(),
            'latest_submission_at' => $form->submissions()->latest()->value('created_at'),
            'fields' => $form->fields()->get(['key', 'label', 'type', 'is_required']),
        ]);
    }
}
