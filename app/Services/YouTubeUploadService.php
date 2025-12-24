<?php

namespace App\Services;

use Google\Client;
use Google\Service\YouTube;
use App\Models\access_token;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class YouTubeUploadService
{
    protected Client $client;
    protected int $chunkSize = 5 * 1024 * 1024; // 5MB chunks (faster upload)
    
    public function __construct()
    {
        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/google_credentials.json'));
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
    }

    /**
     * Upload video to YouTube with metadata
     */
    public function uploadVideo(
        string $videoPath,
        string $channelId,
        int $videoLengthHours = 10,
        string $privacy = 'public'
    ): array {
        if (!file_exists($videoPath)) {
            throw new \RuntimeException("Video file not found: {$videoPath}");
        }

        // Refresh access token if needed
        $this->refreshAccessToken($channelId);

        $youtube = new YouTube($this->client);

        // Generate metadata using AI
        $title = $this->generateTitle($videoLengthHours);
        $description = $this->generateDescription();

        // Create video snippet
        $snippet = new YouTube\VideoSnippet();
        $snippet->setTitle($title);
        $snippet->setDescription($description);
        $snippet->setTags($this->getOptimizedTags());
        $snippet->setCategoryId(24); // Entertainment

        // Set video status
        $status = new YouTube\VideoStatus();
        $status->setPrivacyStatus($privacy);

        // Create video object
        $video = new YouTube\Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Upload video with chunked transfer
        Log::info('Starting video upload', ['file_size' => filesize($videoPath)]);
        
        $videoId = $this->performChunkedUpload($youtube, $video, $videoPath);
        
        Log::info('Video uploaded successfully', ['video_id' => $videoId]);

        return [
            'video_id' => $videoId,
            'title' => $title,
            'url' => "https://www.youtube.com/watch?v={$videoId}"
        ];
    }

    /**
     * Perform chunked video upload
     */
    protected function performChunkedUpload(YouTube $youtube, YouTube\Video $video, string $videoPath): string
    {
        $this->client->setDefer(true);
        $insertRequest = $youtube->videos->insert('snippet,status', $video);

        $media = new \Google\Http\MediaFileUpload(
            $this->client,
            $insertRequest,
            'video/*',
            null,
            true,
            $this->chunkSize
        );
        $media->setFileSize(filesize($videoPath));

        $status = false;
        $handle = fopen($videoPath, 'rb');
        
        $uploadedBytes = 0;
        $totalBytes = filesize($videoPath);
        
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $this->chunkSize);
            $status = $media->nextChunk($chunk);
            
            $uploadedBytes += strlen($chunk);
            $progress = round(($uploadedBytes / $totalBytes) * 100, 2);
            
            if ($progress % 10 == 0) { // Log every 10%
                Log::info("Upload progress: {$progress}%");
            }
        }
        
        fclose($handle);
        $this->client->setDefer(false);

        return $status['id'];
    }

    /**
     * Upload thumbnail to YouTube video
     */
    public function uploadThumbnail(string $videoId, string $channelId, string $thumbnailPath = null): void
    {
        $thumbnailPath = $thumbnailPath ?? storage_path('app/public/merged_image.png');

        if (!file_exists($thumbnailPath)) {
            Log::warning('Thumbnail file not found', ['path' => $thumbnailPath]);
            return;
        }

        $this->refreshAccessToken($channelId);
        $youtube = new YouTube($this->client);

        $youtube->thumbnails->set($videoId, [
            'data' => file_get_contents($thumbnailPath),
            'mimeType' => 'image/jpeg',
            'uploadType' => 'multipart',
        ]);

        Log::info('Thumbnail uploaded successfully', ['video_id' => $videoId]);
    }

    /**
     * Refresh access token if expired (with caching)
     */
    protected function refreshAccessToken(string $channelId): void
    {
        // Cache the access token for 50 minutes (tokens expire in 60 minutes)
        $cacheKey = "youtube_token_{$channelId}";
        
        $tokenData = Cache::remember($cacheKey, now()->addMinutes(50), function () use ($channelId) {
            $accessTokenModel = access_token::where('channel_id', $channelId)->firstOrFail();

            return [
                'access_token' => $accessTokenModel->access_token,
                'refresh_token' => $accessTokenModel->refresh_token,
                'expires_in' => $accessTokenModel->expires_at,
                'scope' => $accessTokenModel->scope,
                'token_type' => $accessTokenModel->token_type,
            ];
        });

        $this->client->setAccessToken($tokenData);

        // Refresh if expired
        if ($this->client->isAccessTokenExpired()) {
            Log::info('Access token expired, refreshing...');
            
            $this->client->fetchAccessTokenWithRefreshToken($tokenData['refresh_token']);
            $newAccessToken = $this->client->getAccessToken();

            // Update database
            $accessTokenModel = access_token::where('channel_id', $channelId)->first();
            $accessTokenModel->update([
                'access_token' => $newAccessToken['access_token'],
                'refresh_token' => $newAccessToken['refresh_token'] ?? $accessTokenModel->refresh_token,
                'expires_at' => $newAccessToken['expires_in'],
            ]);

            // Update cache
            Cache::put($cacheKey, $newAccessToken, now()->addMinutes(50));
            
            Log::info('Access token refreshed successfully');
        }
    }

    /**
     * Generate AI-powered title using OpenAI API
     */
    protected function generateTitle(int $hours): string
    {
        $apiKey = config('services.openai.key');
        
        if (!$apiKey) {
            Log::warning('OpenAI API key not configured, using fallback title');
            return "White Noise for Babies | {$hours} Hours of Peaceful Sleep | Soothe Crying Infant";
        }

        $prompt = $this->buildTitlePrompt($hours);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a YouTube SEO expert specializing in baby sleep content.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $title = trim($response->json()['choices'][0]['message']['content']);
                Log::info('AI title generated', ['title' => $title]);
                return $title;
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate AI title', ['error' => $e->getMessage()]);
        }

        // Fallback title
        return "White Noise for Babies | {$hours} Hours of Peaceful Sleep | Soothe Crying Infant";
    }

    /**
     * Generate AI-powered description
     */
    protected function generateDescription(): string
    {
        $apiKey = config('services.openai.key');
        
        if (!$apiKey) {
            return $this->getFallbackDescription();
        }

        $prompt = "Write a 200-word YouTube description for a baby white noise video. " .
                  "Include SEO keywords: white noise for babies, baby sleep, colic relief, infant sleep aid. " .
                  "Explain benefits and how to use. Professional tone. No emojis or hashtags.";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a YouTube SEO expert.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 400,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $description = trim($response->json()['choices'][0]['message']['content']);
                return $description . "\n\n" . $this->getKeywordsSection();
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate AI description', ['error' => $e->getMessage()]);
        }

        return $this->getFallbackDescription();
    }

    /**
     * Build title generation prompt
     */
    protected function buildTitlePrompt(int $hours): string
    {
        return "Write ONE YouTube video title under 100 characters for a {$hours}-hour white noise video " .
               "for babies. Use emotional keywords: soothe, calm, peaceful, magic sound, fall asleep fast. " .
               "SEO-optimized. No emojis. Return only the title.";
    }

    /**
     * Get fallback description
     */
    protected function getFallbackDescription(): string
    {
        return "This white noise video is specially designed to help babies fall asleep faster and sleep deeper. " .
               "The continuous, gentle sound helps soothe crying infants and provides colic relief. " .
               "Perfect for naps, bedtime, or anytime your baby needs calming. " .
               "Features 10 hours of uninterrupted soothing sounds with no ads.\n\n" .
               $this->getKeywordsSection();
    }

    /**
     * Get keywords section for description
     */
    protected function getKeywordsSection(): string
    {
        return "Keywords: white noise for babies, baby sleep sounds, soothing baby sleep, colic relief, " .
               "infant sleep aid, baby white noise 10 hours, calm baby crying, sleep music for newborns, " .
               "white noise for sleeping, baby sleep music";
    }

    /**
     * Get optimized tags for YouTube
     */
    protected function getOptimizedTags(): array
    {
        return [
            'white noise for babies',
            'baby sleep',
            'white noise',
            'baby white noise',
            'colic relief',
            'infant sleep',
            'baby crying',
            'sleep sounds',
            'baby sleep sounds',
            'colicky baby',
            'soothe baby',
            'calm baby',
            '10 hours',
            'sleep aid',
            'infant',
        ];
    }

    /**
     * Set chunk size for upload
     */
    public function setChunkSize(int $bytes): self
    {
        $this->chunkSize = $bytes;
        return $this;
    }
}
