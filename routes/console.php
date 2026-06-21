<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('regattas:update-statuses')->everyMinute();
Schedule::command('news:publish-to-telegram')->everyMinute()->withoutOverlapping();
Schedule::command('model:prune')->daily();
