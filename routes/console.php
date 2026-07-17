<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Spatie\Health\Commands\RunHealthChecksCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ///// Health Checks (Horizon) ///////
Schedule::command(RunHealthChecksCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(fn () => Schema::hasTable('health_check_result_history_items'));

// ///// Midtrial Emails ///////
Schedule::command('polydock:dispatch-midtrial-emails')
    ->hourlyAt(5)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-midtrial-emails')
    ->hourlyAt(35)
    ->withoutOverlapping();

// ///// One Day Left Emails ///////
Schedule::command('polydock:dispatch-one-day-left-emails')
    ->hourlyAt(15)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-one-day-left-emails')
    ->hourlyAt(45)
    ->withoutOverlapping();

// ///// Trial Complete Emails ///////
Schedule::command('polydock:dispatch-trial-complete-emails')
    ->hourlyAt(30)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-trial-complete-emails')
    ->hourlyAt(0)
    ->withoutOverlapping();

// ///// Trial Complete Stage Removal ///////
Schedule::command('polydock:dispatch-trial-complete-stage-removal')
    ->hourlyAt(45)
    ->withoutOverlapping();

Schedule::command('polydock:dispatch-trial-complete-stage-removal')
    ->hourlyAt(15)
    ->withoutOverlapping();

// ///// Remove Unclaimed Instances ///////
// Schedule::command('polydock:remove-unclaimed-instances --force --limit=5')
//     ->hourly()
//     ->withoutOverlapping();

// ///// Mark Stuck Instances as Failed ///////
Schedule::command('polydock:mark-stuck-instances-failed --threshold=30')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// ///// Project Purge (full Lagoon project deletion after grace period) ///////
Schedule::command('polydock:dispatch-project-purge')
    ->everyTenMinutes()
    ->withoutOverlapping();

// ///// Cadence-based redeploys (upgrade rollouts) ///////
Schedule::command('polydock:dispatch-scheduled-redeploys')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// ///// Poll in-flight Lagoon deployment runs ///////
Schedule::command('polydock:deployments:poll')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// ///// Audit Log Retention ///////
Schedule::command('activitylog:clean')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

// ///// Operational Instance Log Retention ///////
Schedule::command('polydock:prune-instance-logs')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

// ///// Horizon queue metrics snapshots ///////
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
