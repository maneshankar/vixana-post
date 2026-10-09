<?php

use App\Services\AiContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Automation & Webhook API Routes
|--------------------------------------------------------------------------
*/

// Healthcheck
Route::get('/health', function () {
    return response()->json([
        'status' => 'online',
        'timestamp' => now()->toIso8601String(),
        'app' => 'Mixpost Vercel Serverless'
    ]);
});

// Trigger AI post generation and scheduling via Webhook / REST
Route::post('/automation/generate-post', function (Request $request, AiContentService $aiService) {
    $secret = env('CRON_SECRET');
    $auth = $request->header('Authorization') ?: $request->query('token');
    $auth = str_replace('Bearer ', '', $auth);

    if (!empty($secret) && $auth !== $secret) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $topic = $request->input('topic', env('AI_POSTING_TOPICS', 'Latest tech trends & AI productivity tips'));
    $hours = (int)$request->input('schedule_hours', 2);

    $exitCode = Artisan::call('mixpost:generate-ai-posts', [
        '--topic' => $topic,
        '--schedule-hours' => $hours,
    ]);

    return response()->json([
        'status' => $exitCode === 0 ? 'success' : 'error',
        'topic' => $topic,
        'scheduled_in_hours' => $hours,
        'output' => Artisan::output()
    ]);
});
