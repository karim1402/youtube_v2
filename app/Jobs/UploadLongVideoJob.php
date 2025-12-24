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
use App\Helpers\LongVideoHelper;
use App\Models\access_token;

/**
 * UploadLongVideoJob - Creates and uploads long videos (1h, 3h, 10h) sequentially
 * Uploads one video, deletes it, then proceeds to the next to conserve storage
 */
class UploadLongVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $client;
    public $timeout = 43200; // 12 hours max

    public function __construct() {}

    public function handle()
    {
        Log::info('UploadLongVideoJob started - Processing 1h, 3h, 10h videos sequentially');

        $targets = [1, 3, 10]; // Hours

        foreach ($targets as $hours) {
            Log::info("=== Starting {$hours}h video ===");

            // 1. Create the video
            $videoPath = LongVideoHelper::processVideo($hours);

            if (!$videoPath || !file_exists($videoPath)) {
                Log::error("Failed to create {$hours}h video");
                continue;
            }

            Log::info("Video created: $videoPath");

            // 2. Generate thumbnail
            LongVideoHelper::overlayImages();

            // 3. Upload to YouTube
            $videoId = $this->uploadToYouTube($videoPath, $hours);

            if ($videoId) {
                Log::info("Successfully uploaded {$hours}h video. ID: $videoId");
            } else {
                Log::error("Failed to upload {$hours}h video");
            }

            // 4. Delete video to free space
            if (file_exists($videoPath)) {
                unlink($videoPath);
                Log::info("Deleted {$hours}h video to free storage");
            }

            Log::info("=== Completed {$hours}h video ===");
        }

        Log::info('UploadLongVideoJob completed - All videos processed');
    }

    private function uploadToYouTube($videoPath, $hours)
    {
        $channelId = '2';

        $this->client = new Client();
        $this->refresh_access_token($channelId);

        $this->client->setAuthConfig(storage_path('app/google_credentials.json'));
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        ini_set('max_execution_time', 14400);
        set_time_limit(14400);

        $accessTokenModel = access_token::where('channel_id', $channelId)->first();

        $cer = [
            'access_token' => $accessTokenModel->access_token,
            'refresh_token' => $accessTokenModel->refresh_token,
            'expires_in' => $accessTokenModel->expires_at,
            'scope' => $accessTokenModel->scope,
            'token_type' => $accessTokenModel->token_type,
            'refresh_token_expires_in' => $accessTokenModel->refresh_token_expires_in
        ];

        $this->client->setAccessToken($cer);
        $youtube = new YouTube($this->client);

        // Create snippet
        $snippet = new YouTube\VideoSnippet();

        $titlePrompt = "this is list of my youtube video its video about white noise for baby make me titel to get most views based on youtube algrtiom The title must be under 100 characters, fully optimized for YouTube SEO, and written in clear, fluent English 
            Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound, fall asleep fast), and mirror the successful rhythm and phrasing of the examples above. You may use pipes | or dashes - to separate parts naturally, but avoid emojis, clickbait, or artificial language.
            Return only the title — no commentary or explanation. Return only the final title — no extra explanation.
            the video length is $hours hours
            1-White Noise for Babies - Soothe Your Fussy Baby to Sleep Fast with $hours Hours of Relaxing Sounds
            2-White Noise for Babies | Comfort Your Crying Newborn with This {$hours}-Hour Calming Sleep Sound
            3-Colicky Baby Sleeps Soundly Tonight | $hours Hours White Noise for Babies | No Ads Peaceful Sound
            4-Crying Infant Sleeps with White Noise | Magic Sound for Babies | Colicky Baby Sleep Solution
            5-White Noise for Babies | Colicky Baby Sleeps Like Magic | $hours Hours of Peaceful Sleeping Sound
            ";

        $title = LongVideoHelper::generateText($titlePrompt);

        $descriptionPrompt = "Write a full YouTube video description for a white noise video made specifically for babies. The video is {$hours} hours long and designed to help newborns, infants, or toddlers fall asleep faster, sleep deeper, or calm down when crying. It may also help soothe colicky babies. The description should be 150–300 words long, written in natural and professional English, and fully optimized for YouTube SEO and search. Include high-traffic keywords. The tone should be calm, reassuring, and directed toward parents. Do not include emojis, timestamps, hashtags, or links. Return only the description.";

        $description = LongVideoHelper::generateText($descriptionPrompt);
        $description = str_replace('*', '', $description);

        $fullDescription = "$description\n\n" .
            "Keywords:#babywhitenoise #colickybaby #calmingbabysounds #sleepaidforinfants #cryingbabyrelief #soothecryinginfant #relaxingwhitenoise #babysleepsounds #magicwhitenoise #babynaptime #sleepingbabynoise #infantsleep #baby #whitenoiseforbabies #whitenoise #babysleepmusic #lullaby\n\n" .
            "white noise for babies,for babies,white noise,baby white noise,white noise baby,white noise for sleeping,sleep,sleep sounds,womb sounds,colic,baby colic,colicky baby,baby,soothe,soothing,dark screen,black screen,baby sleeping,infant,crying,insomnia,bedtime,newborn,parent,fall asleep,mom,dad,kid,parenting,calm,baby sleep,naptime,baby crying,soothe crying baby";

        $snippet->setTitle($title);
        $snippet->setDescription($fullDescription);
        $snippet->setTags([
            "infant sleep sound", "sleep sound", "infant", "happy baby", "white noise",
            "pink noise", "brown noise", "white noise for babies", "white noise sleep",
            "sleep", "baby", "baby sleep", "white noise for sleeping", "colic", "crying",
            "baby crying", "put baby to sleep", "parent", "babies", "sleeping", "mom",
            "sleep noise", "soothing", "dad", "mother", "calm", "fall asleep", "sleep sounds",
            "womb", "colicky", "child", "sleep aid", "baby colic", "for sleep",
            "$hours hours", "white noise baby", "baby white noise", "colicky baby",
            "sleeps", "white noise sound", "infant sleep"
        ]);
        $snippet->setCategoryId(24);

        $status = new YouTube\VideoStatus();
        $status->setPrivacyStatus('public');

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

        if ($uploadStatus) {
            $this->uploadThumbnail($uploadStatus['id']);
            return $uploadStatus['id'];
        }

        return null;
    }

    public function uploadThumbnail($id)
    {
        $accessTokenModel = access_token::where('channel_id', '2')->first();

        $cer = [
            'access_token' => $accessTokenModel->access_token,
            'refresh_token' => $accessTokenModel->refresh_token,
            'expires_in' => $accessTokenModel->expires_at,
            'scope' => $accessTokenModel->scope,
            'token_type' => $accessTokenModel->token_type,
            'refresh_token_expires_in' => $accessTokenModel->refresh_token_expires_in
        ];
        $this->client->setAccessToken($cer);

        $youtube = new YouTube($this->client);

        $thumbnailPath = storage_path('app/public/merged_image_long.png');

        $response = $youtube->thumbnails->set(
            $id,
            [
                'data' => file_get_contents($thumbnailPath),
                'mimeType' => 'image/jpeg',
                'uploadType' => 'multipart',
            ]
        );

        return $response;
    }

    public function refresh_access_token($channelId)
    {
        $accessTokenModel = access_token::where('channel_id', $channelId)->first();

        $cer = [
            'access_token' => $accessTokenModel->access_token,
            'refresh_token' => $accessTokenModel->refresh_token,
            'expires_in' => $accessTokenModel->expires_at,
            'scope' => $accessTokenModel->scope,
            'token_type' => $accessTokenModel->token_type,
            'refresh_token_expires_in' => $accessTokenModel->refresh_token_expires_in
        ];

        $this->client->setAccessToken($cer);

        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($accessTokenModel->refresh_token);
            $newAccessToken = $this->client->getAccessToken();

            $accessTokenModel->access_token = $newAccessToken['access_token'];
            $accessTokenModel->refresh_token = $newAccessToken['refresh_token'];
            $accessTokenModel->expires_at = $newAccessToken['expires_in'];
            $accessTokenModel->scope = $newAccessToken['scope'];
            $accessTokenModel->token_type = $newAccessToken['token_type'];
            $accessTokenModel->refresh_token_expires_in = $newAccessToken['refresh_token_expires_in'];
            $accessTokenModel->save();
        }
    }
}
