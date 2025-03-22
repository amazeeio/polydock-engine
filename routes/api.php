<?php

use App\Http\Controllers\Api\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [RegisterController::class, 'processRegister'])->name('register.process');
Route::get('/register/{uuid}', [RegisterController::class, 'showRegister'])->name('register.show');

Route::match(['get', 'post'], '/instance/{uuid}/health/{status}', [
    \App\Http\Controllers\Api\PolydockInstanceHealthController::class, 
    '__invoke'
])->name('api.instance.health');