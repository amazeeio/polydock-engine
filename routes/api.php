<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PolydockInstanceHealthController;
use App\Http\Controllers\Api\RegionsController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Middleware\EnsureInstancesReadAbility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

Route::post('/register', [RegisterController::class, 'processRegister'])->name('register.process');
Route::get('/register/{uuid}', [RegisterController::class, 'showRegister'])->name('register.show');

Route::get('/regions', [RegionsController::class, 'index'])->name('regions.index');

Route::prefix('/v1')->group(function (): void {
    Route::get('/instances/{uuid}', [RegisterController::class, 'showRegister'])
        ->middleware(['auth:sanctum', EnsureInstancesReadAbility::class])
        ->name('v1.instances.show');
});

Route::match(['get', 'post'], '/instance/{uuid}/health/{status}', [
    PolydockInstanceHealthController::class,
    '__invoke',
])->name('api.instance.health');
