<?php

namespace App\Console\Commands;

use App\Services\AiContentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAiPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mixpost:generate-ai-posts {--topic=} {--schedule-hours=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate social media posts using AI and schedule them into Mixpost queue';

    /**
     * Execute the console command.
     */
    public function handle(AiContentService $aiService)
    {
        $this->info('Starting AI Social Media Content Generation...');

        $topic = $this->option('topic') ?: env('AI_POSTING_TOPICS', 'Latest updates in AI, software development, and tech productivity tips');
        $tone = env('AI_POSTING_TONE', 'insightful and engaging');
        $hoursOffset = (int)$this->option('schedule-hours');

        $this->info("Topic: {$topic}");
        $this->info("Tone: {$tone}");

        try {
            $generated = $aiService->generatePost($topic, $tone);
            $imageUrl = $aiService->generateImageUrl($generated['image_prompt'] ?? $topic);

            $this->info("Generated Post Copy:");
            $this->line($generated['text'] ?? 'No text generated');
            $this->info("Generated Image URL: {$imageUrl}");

            // Mixpost database scheduling
            $accounts = DB::table('mixpost_accounts')->get();

            if ($accounts->isEmpty()) {
                $this->warn('No connected social accounts found in Mixpost. Please connect accounts in the Mixpost dashboard first.');
                return self::SUCCESS;
            }

            $scheduledAt = Carbon::now()->addHours($hoursOffset);

            $postId = DB::table('mixpost_posts')->insertGetId([
                'status' => 'SCHEDULED',
                'scheduled_at' => $scheduledAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert post versions for each account / default
            DB::table('mixpost_post_versions')->insert([
                'post_id' => $postId,
                'account_id' => 0, // default version
                'is_original' => 1,
                'content' => json_encode([
                    [
                        'type' => 'text',
                        'data' => ['text' => $generated['text'] ?? '']
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link accounts
            foreach ($accounts as $account) {
                DB::table('mixpost_post_accounts')->insert([
                    'post_id' => $postId,
                    'account_id' => $account->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->info("Successfully scheduled post ID: {$postId} for {$scheduledAt->toDateTimeString()} across {$accounts->count()} accounts.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error generating AI post: ' . $e->getMessage());
            Log::error('AI Post Generation Failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
