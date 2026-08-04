<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiFormRequest;
use App\Services\AiFormDesigner;

class AiFormController extends Controller
{
    public function __invoke(AiFormRequest $request, AiFormDesigner $designer)
    {
        $schema = $request->validated('schema');

        return response()->json([
            'schema' => is_array($schema)
                ? $designer->edit($schema, $request->validated('prompt'))
                : $designer->generate(
                    $request->validated('prompt'),
                    $request->validated('title')
                ),
        ]);
    }
}
