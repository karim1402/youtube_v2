<?php

namespace App\Jobs;

use Google\Client;
use Google\Service\YouTube;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Helpers\YoutubeShortsHelper;
use App\Models\access_token;

class UploadYoutubeShortsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $client;
    public $timeout = 3600;

    public function __construct() {}

    public function handle()
    {
        Log::info('UploadYoutubeShortsJob started');

        // Generate Video
        $result = YoutubeShortsHelper::generateVideo();
        if (!$result || !file_exists($result['path'])) {
            Log::error("Failed to generate shorts video");
            return;
        }

        $videoPath = $result['path'];
        $duration = $result['duration'];
        Log::info("Shorts video created: {$duration}s");

        // Auth
        $channelId = '2';
        $this->client = new Client();
        $this->refresh_access_token($channelId);
        $this->client->setAuthConfig(storage_path('app/google_credentials.json'));
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        
        $accessTokenModel = access_token::where('channel_id', $channelId)->first();
        $this->client->setAccessToken([
            'access_token' => $accessTokenModel->access_token,
            'refresh_token' => $accessTokenModel->refresh_token,
            'expires_in' => $accessTokenModel->expires_at,
            'scope' => $accessTokenModel->scope,
            'token_type' => $accessTokenModel->token_type,
            'refresh_token_expires_in' => $accessTokenModel->refresh_token_expires_in
        ]);

        $youtube = new YouTube($this->client);

        // Metadata
        $snippet = new YouTube\VideoSnippet();
        
        $titlePrompt = "Generate a catchy, viral title for a YouTube Short about baby sleep white noise. 
        Duration: {$duration} seconds.
        Must include #Shorts.
        Examples:
        - Magic Sleep Sound for Babies 😴 #Shorts
        - Instant Baby Sleep White Noise 🌙 #Shorts
        - Stop Crying in Seconds! 👶 #Shorts
        Return only the title.";
        
        $title = YoutubeShortsHelper::generateText($titlePrompt);
        $title = str_replace('"', '', $title); // Clean quotes

        $descPrompt = "Generate a short, SEO-optimized description for a YouTube Short about baby sleep white noise.
        Include hashtags: #Shorts #WhiteNoise #BabySleep #Parenting.
        Return only the description.";
        
        $description = YoutubeShortsHelper::generateText($descPrompt);

        $snippet->setTitle($title);
        $snippet->setDescription($description);
        $snippet->setTags(['Shorts', 'white noise', 'baby sleep', 'parenting', 'sleep sounds']);
        $snippet->setCategoryId(24);

        $status = new YouTube\VideoStatus();
        $status->setPrivacyStatus('public');
        // Note: Cannot set "Premiere" via API.

        $video = new YouTube\Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Upload
        $chunkSizeBytes = 1 * 1024 * 1024;
        $this->client->setDefer(true);
        $insertRequest = $youtube->videos->insert('snippet,status', $video);

        $media = new \Google\Http\MediaFileUpload(
            $this->client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($videoPath));

        $uploadStatus = false;
        $handle = fopen($videoPath, 'rb');
        while (!$uploadStatus && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $uploadStatus = $media->nextChunk($chunk);
        }
        fclose($handle);

        $this->client->setDefer(false);
        Log::info('Shorts upload finished');
        
        // Cleanup
        @unlink($videoPath);

        // Add to all playlists
        \App\Helpers\YouTubePlaylistHelper::addVideoToAllPlaylists($this->client, $uploadStatus['id']);

        return response()->json(['message' => 'Shorts uploaded', 'video_id' => $uploadStatus['id']]);
    }

    public function refresh_access_token($channelId)
    {
        $accessTokenModel = access_token::where('channel_id', $channelId)->first();
        
        // Set the full token array so the client has the refresh token to use
        $this->client->setAccessToken([
            'access_token' => $accessTokenModel->access_token,
            'refresh_token' => $accessTokenModel->refresh_token,
            'expires_in' => $accessTokenModel->expires_at,
            'created' => time(), // Optional but good practice
        ]);
        
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($accessTokenModel->refresh_token);
            $newAccessToken = $this->client->getAccessToken();
            
            $accessTokenModel->access_token = $newAccessToken['access_token'];
            $accessTokenModel->expires_at = $newAccessToken['expires_in'];
            
            // Refresh token is usually NOT returned on refresh, only on first auth
            if (isset($newAccessToken['refresh_token'])) {
                $accessTokenModel->refresh_token = $newAccessToken['refresh_token'];
            }
            
            $accessTokenModel->save();
        }
    }
}
