<?php

use App\Http\Controllers\Api\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [RegisterController::class, 'processRegister'])->name('register.process');
Route::get('/register/{id}', [RegisterController::class, 'showRegister'])->name('register.show');