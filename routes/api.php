<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthenticatedApiController;
use App\Http\Controllers\Api\PolydockInstanceHealthController;
use App\Http\Controllers\Api\RegionsController;
use App\Http\Controllers\Api\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // Routes consumed by MoaD
    Route::get('/store-apps', [AuthenticatedApiController::class, 'getStoreApps'])->name('api.store-apps');
    Route::get('/instances', [AuthenticatedApiController::class, 'getInstances'])->name('api.instances.get');
    Route::post('/instance', [AuthenticatedApiController::class, 'createInstance'])->name('api.instance.create');
    Route::get('/instance/{uuid}/status', [AuthenticatedApiController::class, 'getInstanceStatus'])->name('api.instance.status');
    Route::delete('/instance/{uuid}', [AuthenticatedApiController::class, 'deleteInstance'])->name('api.instance.delete');
});

Route::post('/register', [RegisterController::class, 'processRegister'])->name('register.process');
Route::get('/register/{uuid}', [RegisterController::class, 'showRegister'])->name('register.show');

Route::get('/regions', [RegionsController::class, 'index'])->name('regions.index');

Route::match(['get', 'post'], '/instance/{uuid}/health/{status}', [
    PolydockInstanceHealthController::class,
    '__invoke',
])->name('api.instance.health');
