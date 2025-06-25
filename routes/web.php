<?php

use Illuminate\Support\Facades\Route;
use App\Models\PolydockAppInstance;

Route::get('/', function () {
    if(!config('polydock.redirect_landing_page_to')) {
        return view('welcome');
    }
    
    return redirect(config('polydock.redirect_landing_page_to'));
});

Route::get('/app-instances/{appInstance}', function (PolydockAppInstance $appInstance) {
    if ($appInstance->app_one_time_login_url && !$appInstance->oneTimeLoginUrlHasExpired()) {
        return redirect()->away($appInstance->app_one_time_login_url, 302);
    }
    
    if ($appInstance->app_url) {
        return redirect()->away($appInstance->app_url, 302);
    }
    
    abort(404, 'No URL available for this app instance');
})->name('app-instances.show');
