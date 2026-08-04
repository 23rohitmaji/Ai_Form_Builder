<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use App\Services\FormSchemaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $forms = $request->user()->forms()
            ->withCount('submissions')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return FormResource::collection($forms);
    }

    public function store(StoreFormRequest $request, FormSchemaService $schemas)
    {
        $data = $request->validated();
        $form = $request->user()->forms()->create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
            'description' => $data['description'] ?? null,
            'is_published' => $data['is_published'] ?? false,
            'store_submissions' => $data['store_submissions'] ?? true,
            'settings' => $data['settings'] ?? null,
        ]);

        $schemas->syncFields($form, $data['fields']);

        return (new FormResource($form->load('fields')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Form $form)
    {
        abort_unless($form->user_id === $request->user()->id, 403);

        return new FormResource($form->load('fields'));
    }

    public function update(UpdateFormRequest $request, Form $form, FormSchemaService $schemas)
    {
        abort_unless($form->user_id === $request->user()->id, 403);

        $data = $request->validated();
        $oldSlug = $form->slug;
        $form->update(collect($data)->except('fields')->all());

        if (array_key_exists('fields', $data)) {
            $schemas->syncFields($form, $data['fields']);
        }

        Cache::forget("forms.public.$oldSlug");
        Cache::forget("forms.public.$form->slug");

        return new FormResource($form->refresh()->load('fields'));
    }

    public function destroy(Request $request, Form $form)
    {
        abort_unless($form->user_id === $request->user()->id, 403);
        Cache::forget("forms.public.$form->slug");
        $form->delete();

        return response()->noContent();
    }

    public function public(string $slug)
    {
        $formId = Cache::remember("forms.public.$slug", 300, fn () => Form::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->value('id'));

        abort_unless($formId, 404, 'Published form not found.');

        $form = Form::with('fields')->findOrFail($formId);

        return new FormResource($form);
    }
}
