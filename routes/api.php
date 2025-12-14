<?php

declare(strict_types=1);

use App\Http\Controllers\API\V1\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/login', LoginController::class);

Route::middleware('auth:api')->group(function () {
   Route::get('/user', function (Request $request) {
       return $request->user();
   });
});

