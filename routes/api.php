<?php

declare(strict_types=1);

use App\Http\Controllers\API\V1\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:api');

Route::post('/login', LoginController::class);
