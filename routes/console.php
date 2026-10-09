<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Task Scheduling
|--------------------------------------------------------------------------
*/

// Process Mixpost core scheduler every minute
Schedule::command('mixpost:run-scheduled-posts')->everyMinute();

// Automatically generate and schedule fresh AI posts every day or custom interval
$autoAiHours = env('AI_POST_INTERVAL_HOURS', 12);
if ($autoAiHours > 0) {
    Schedule::command('mixpost:generate-ai-posts')->cron("0 */{$autoAiHours} * * *");
}
