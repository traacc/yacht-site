<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('regattas:update-statuses')->everyMinute();
Schedule::command('news:publish-to-telegram')->everyMinute()->withoutOverlapping();
Schedule::command('news:publish-to-vk')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('model:prune')->daily();
