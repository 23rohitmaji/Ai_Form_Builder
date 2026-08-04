<?php

use App\Http\Controllers\Api\AiFormController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FormAnalyticsController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\FormSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/public/forms/{slug}', [FormController::class, 'public']);
Route::post('/public/forms/{slug}/submissions', [FormSubmissionController::class, 'store']);

Route::middleware('api.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/ai/forms', AiFormController::class);
    Route::apiResource('/forms', FormController::class);
    Route::get('/forms/{form}/analytics', FormAnalyticsController::class);
    Route::get('/forms/{form}/submissions', [FormSubmissionController::class, 'index']);
    Route::get('/forms/{form}/submissions/export', [FormSubmissionController::class, 'export']);
    Route::get('/forms/{form}/submissions/{submission}', [FormSubmissionController::class, 'show']);
});
