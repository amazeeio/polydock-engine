<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/////// Midtrial Emails ///////
Schedule::command('polydock:dispatch-midtrial-emails')
    ->hourlyAt(5)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-midtrial-emails')
    ->hourlyAt(35)
    ->withoutOverlapping();

/////// One Day Left Emails ///////
Schedule::command('polydock:dispatch-one-day-left-emails')
    ->hourlyAt(15)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-one-day-left-emails')
    ->hourlyAt(45)
    ->withoutOverlapping();

/////// Trial Complete Emails ///////
Schedule::command('polydock:dispatch-trial-complete-emails')
    ->hourlyAt(30)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-trial-complete-emails')
    ->hourlyAt(0)
    ->withoutOverlapping();

/////// Trial Complete Stage Removal ///////
Schedule::command('polydock:dispatch-trial-complete-stage-removal')
    ->hourlyAt(45)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-trial-complete-stage-removal')
    ->hourlyAt(15)
    ->withoutOverlapping();

/////// Remove Unclaimed Instances ///////
// Schedule::command('polydock:remove-unclaimed-instances --force --limit=5')
//     ->hourly()
//     ->withoutOverlapping();