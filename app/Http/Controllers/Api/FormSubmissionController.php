<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Http\Resources\FormSubmissionResource;
use App\Jobs\ProcessFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormSchemaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionController extends Controller
{
    public function index(Form $form)
    {
        abort_unless($form->user_id === request()->user()->id, 403);

        $query = $form->submissions()->latest();
        $search = request()->string('search')->trim()->toString();

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('status', 'like', "%$search%")
                    ->orWhere('answers', 'like', "%$search%")
                    ->orWhere('metadata', 'like', "%$search%");
            });
        }

        return FormSubmissionResource::collection(
            $query->paginate(request()->integer('per_page', 5))
        );
    }

    public function store(StoreSubmissionRequest $request, string $slug, FormSchemaService $schemas)
    {
        $form = Form::where('slug', $slug)->where('is_published', true)->with('fields')->firstOrFail();
        $answers = $schemas->validateAnswers($form, $request->validated('answers'));

        if (! $form->store_submissions) {
            return response()->json([
                'message' => 'Submission validated. This form is configured not to store responses.',
                'data' => [
                    'form_id' => $form->id,
                    'answers' => $answers,
                    'status' => 'not_stored',
                    'metadata' => null,
                    'processed_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ], 202);
        }

        $submission = $form->submissions()->create([
            'answers' => $answers,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                ...($request->validated('metadata') ?? []),
            ],
        ]);

        ProcessFormSubmission::dispatch($submission);

        return (new FormSubmissionResource($submission->refresh()))->response()->setStatusCode(201);
    }

    public function show(Form $form, FormSubmission $submission)
    {
        abort_unless($form->user_id === request()->user()->id && $submission->form_id === $form->id, 403);

        return new FormSubmissionResource($submission);
    }

    public function export(Form $form): StreamedResponse
    {
        abort_unless($form->user_id === request()->user()->id, 403);

        return response()->streamDownload(function () use ($form) {
            $fields = $form->fields()
                ->where('type', '!=', 'section')
                ->orderBy('position')
                ->get(['key', 'label']);
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                ...$fields->map(fn ($field) => $field->label ?: $field->key)->all(),
                'created_at',
            ]);

            $form->submissions()->orderBy('id')->chunk(200, function ($submissions) use ($out, $fields) {
                foreach ($submissions as $submission) {
                    fputcsv($out, [
                        $submission->id,
                        ...$fields->map(fn ($field) => $this->csvValue($submission->answers[$field->key] ?? null))->all(),
                        $submission->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, str($form->slug)->append('-submissions.csv')->toString());
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => is_array($item) ? ($item['name'] ?? json_encode($item)) : $item)
                ->implode('; ');
        }

        return (string) $value;
    }
}
