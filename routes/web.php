<?php

use App\Http\Controllers\Api\AiFormController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FormAnalyticsController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\FormSubmissionController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/f/{slug}', function () {
    return view('welcome');
});

Route::prefix('xapi')->as('xapi.')->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () {
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
});
