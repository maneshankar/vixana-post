<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiContentService
{
    protected string $provider;

    public function __construct()
    {
        $this->provider = config('services.ai.default_provider', 'gemini');
    }

    /**
     * Generate social media post text using configured AI provider.
     */
    public function generatePost(string $topic, string $tone = 'engaging', array $platforms = ['twitter', 'linkedin', 'facebook']): array
    {
        $prompt = "You are an expert social media manager.
Create an engaging social media post about: '{$topic}'.
Tone: {$tone}.
Target platforms: " . implode(', ', $platforms) . ".

Return a JSON object strictly matching this schema:
{
  \"text\": \"The main social media post copy including engaging hook, body, and relevant hashtags (max 280 chars for Twitter, or adapted version)\",
  \"twitter_version\": \"Concise version under 280 characters with hashtags\",
  \"linkedin_version\": \"Professional storytelling version with bullet points and hashtags\",
  \"image_prompt\": \"A descriptive visual prompt to generate an accompanying high-quality image\",
  \"suggested_time_offset_hours\": 2
}
Do not wrap in markdown quotes or extra commentary, return pure JSON.";

        return match ($this->provider) {
            'gemini' => $this->callGemini($prompt),
            'openai' => $this->callOpenAI($prompt),
            'claude' => $this->callClaude($prompt),
            default => $this->callGemini($prompt),
        };
    }

    /**
     * Generate an image URL for the post.
     */
    public function generateImageUrl(string $imagePrompt): string
    {
        $provider = config('services.ai.image_generation.provider', 'pollinations');

        if ($provider === 'unsplash') {
            $accessKey = config('services.ai.image_generation.unsplash_access_key');
            if ($accessKey) {
                $response = Http::get("https://api.unsplash.com/photos/random", [
                    'query' => $imagePrompt,
                    'client_id' => $accessKey,
                    'orientation' => 'landscape'
                ]);
                if ($response->successful()) {
                    return $response->json()['urls']['regular'] ?? '';
                }
            }
        }

        // Default: Free, high-speed Pollinations.ai image generator
        $cleanPrompt = urlencode($imagePrompt . " high quality, professional, 4k, crisp, modern photography");
        return "https://image.pollinations.ai/prompt/{$cleanPrompt}?width=1200&height=630&nologo=true";
    }

    protected function callGemini(string $prompt): array
    {
        $apiKey = config('services.ai.gemini.api_key');
        $model = config('services.ai.gemini.model', 'gemini-1.5-flash');

        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ]);

        if (!$response->successful()) {
            Log::error("Gemini API Error: " . $response->body());
            throw new \Exception("Gemini API error: " . $response->body());
        }

        $content = $response->json('candidates.0.content.parts.0.text', '{}');
        return json_decode($content, true) ?: ['text' => $content];
    }

    protected function callOpenAI(string $prompt): array
    {
        $apiKey = config('services.ai.openai.api_key');
        $model = config('services.ai.openai.model', 'gpt-4o-mini');

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an elite social media copywriter. Always output valid JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ]);

        if (!$response->successful()) {
            Log::error("OpenAI API Error: " . $response->body());
            throw new \Exception("OpenAI API error: " . $response->body());
        }

        $content = $response->json('choices.0.message.content', '{}');
        return json_decode($content, true) ?: ['text' => $content];
    }

    protected function callClaude(string $prompt): array
    {
        $apiKey = config('services.ai.claude.api_key');
        $model = config('services.ai.claude.model', 'claude-3-5-haiku-latest');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json'
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        if (!$response->successful()) {
            Log::error("Claude API Error: " . $response->body());
            throw new \Exception("Claude API error: " . $response->body());
        }

        $content = $response->json('content.0.text', '{}');
        // Clean JSON if needed
        $cleanJson = preg_replace('/^```json\s*|\s*```$/m', '', trim($content));
        return json_decode($cleanJson, true) ?: ['text' => $cleanJson];
    }
}
