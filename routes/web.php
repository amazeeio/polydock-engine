<?php

use App\Http\Controllers\Auth\OktaController;
use App\Http\Controllers\FormController;
use App\Models\PolydockAppInstance;
use Illuminate\Support\Facades\Route;

Route::get('/auth/okta/redirect', [OktaController::class, 'redirect'])->name('okta.redirect');
Route::get('/auth/okta/callback', [OktaController::class, 'callback'])->name('okta.callback');

// Dev-only fake Okta IdP form (see App\Auth\FakeOktaProvider).
Route::get('/fake-okta/authorize', function () {
    abort_unless(config('okta.fake') && app()->environment('local', 'testing'), 404);

    return view('auth.fake-okta', ['state' => (string) request()->query('state')]);
})->name('fake-okta.form');

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
})->name('app-instances.show')->middleware('signed');

Route::get('/f/{formSlug}', [FormController::class, 'show'])
    ->name('forms.show')
    ->middleware('throttle:30,1');
Route::post('/f/{formSlug}', [FormController::class, 'submit'])
    ->name('forms.submit')
    ->middleware('throttle:10,1');
