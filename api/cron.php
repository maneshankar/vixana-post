<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// Prepare tmp directories
foreach (['/tmp/views', '/tmp/cache', '/tmp/sessions', '/tmp/logs'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Security verification
$expectedSecret = env('CRON_SECRET');
$providedSecret = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$providedSecret = str_replace('Bearer ', '', $providedSecret);

if (!empty($expectedSecret) && $providedSecret !== $expectedSecret) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Invalid CRON_SECRET.']);
    exit;
}

header('Content-Type: application/json');

$output = [];
$startTime = microtime(true);

try {
    // 1. Run Laravel / Mixpost Scheduler
    $schedulerOutput = new BufferedOutput();
    Artisan::call('schedule:run', [], $schedulerOutput);
    $output['schedule_run'] = $schedulerOutput->fetch();

    // 2. Process any pending jobs in the queue
    $queueOutput = new BufferedOutput();
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-time' => 45,
        '--tries' => 3
    ], $queueOutput);
    $output['queue_work'] = $queueOutput->fetch();

    $executionTime = round(microtime(true) - $startTime, 2);

    echo json_encode([
        'status' => 'success',
        'timestamp' => date('c'),
        'execution_time_seconds' => $executionTime,
        'details' => $output
    ], JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
