<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/f/{slug}', function () {
    return view('welcome');
});
