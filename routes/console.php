<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('regattas:update-statuses')->everyMinute();
Schedule::command('model:prune')->everyMinute();

Schedule::call(function () {
    Yacht::onlyTrashed()->forceDelete();
})->everyMinute();