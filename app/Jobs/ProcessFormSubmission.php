<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ProcessFormSubmission implements ShouldQueue
{
    use Queueable;

    public function __construct(public FormSubmission $submission)
    {
        //
    }

    public function handle(): void
    {
        $this->submission->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        Cache::forget("forms.{$this->submission->form_id}.analytics");
    }
}
