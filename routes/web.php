<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if(!config('polydock.redirect_landing_page_to')) {
        return view('welcome');
    }
    
    return redirect(config('polydock.redirect_landing_page_to'));
});
