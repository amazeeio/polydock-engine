<?php

use App\Models\PolydockAppInstance;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! config('polydock.redirect_landing_page_to')) {
        return view('welcome');
    }

    return redirect(config('polydock.redirect_landing_page_to'));
});

Route::get('/app-instances/{appInstance}', function (PolydockAppInstance $appInstance) {
    if ($appInstance->app_one_time_login_url && ! $appInstance->oneTimeLoginUrlHasExpired()) {
        return redirect()->away($appInstance->app_one_time_login_url, 302);
    }

    if ($appInstance->app_url) {
        return redirect()->away($appInstance->app_url, 302);
    }

    abort(404, 'No URL available for this app instance');
})->name('app-instances.show');

// OpenAPI - Scribe Custom Routes
Route::get('/api', function () {
    return view('scribe.index');
})->name('scribe');

// OpenAPI JSON endpoint
Route::get('/api/openapi.json', function () {
    $path = \Illuminate\Support\Facades\Storage::disk('local')->path('scribe/openapi.yaml');
    if (!file_exists($path)) {
        abort(404, 'Documentation not generated yet.');
    }

    $yaml = \Symfony\Component\Yaml\Yaml::parseFile($path);
    return response()->json($yaml);
})->name('scribe.json');
