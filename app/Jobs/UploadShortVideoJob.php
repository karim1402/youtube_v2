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
use App\Helpers\ShortVideoHelper;
use App\Models\access_token;

/**
 * UploadShortVideoJob - Creates and uploads short videos (5-30 minutes)
 * Uses 1-minute material clips instead of full-length videos
 */
class UploadShortVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $client;
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info('UploadShortVideoJob started');

        // Create the short video (5-30 minutes randomly)
        $videoMinutes = ShortVideoHelper::runShortVideo();

        Log::info("Short video created: {$videoMinutes} minutes");

        // Generate thumbnail
        ShortVideoHelper::overlayImages();

        $channelId = '2';

        $this->client = new Client();
        $this->refresh_access_token($channelId);

        $this->client->setAuthConfig(storage_path('app/google_credentials.json'));
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        ini_set('max_execution_time', 7200);
        set_time_limit(7200);

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

        $videoPath = storage_path("app/outputs/short_video_final.mp4");

        // Create snippet with AI-generated title and description
        $snippet = new YouTube\VideoSnippet();
        
        $titlePrompt = "this is list of my youtube video its video about white noise for baby make me titel to get most views based on youtube algrtiom The title must be under 100 characters, fully optimized for YouTube SEO, and written in clear, fluent English 
            Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound, fall asleep fast), and mirror the successful rhythm and phrasing of the examples above. You may use pipes | or dashes - to separate parts naturally, but avoid emojis, clickbait, or artificial language.
            Return only the title — no commentary or explanation. Return only the final title — no extra explanation.
            the video length is $videoMinutes minutes
            1-White Noise for Babies - Soothe Your Fussy Baby to Sleep Fast with Relaxing Sounds
            2-White Noise for Babies | Comfort Your Crying Newborn with Calming Sleep Sound
            3-Colicky Baby Sleeps Soundly Tonight | White Noise for Babies | No Ads Peaceful Sound
            4-Crying Infant Sleeps with White Noise | Magic Sound for Babies | Colicky Baby Sleep Solution
            5-White Noise for Babies | Colicky Baby Sleeps Like Magic | Peaceful Sleeping Sound
            6-Colicky Baby Sleeps with Soothing Magic | White Noise for Babies | Without Disruption
            7-Magic Sound for Colicky Baby | White Noise for Babies to Help Infant Sleep Through the Night
            8-Baby White Noise Fan Sounds - White Noise Sound to Help You Fall Asleep
            9-White Noise for Babies - Gentle Sound to Calm Colicky Baby & Help Them Sleep
            10-White Noise for Babies - Magic Sound for Baby Sleep
            ";

        $title = ShortVideoHelper::generateText($titlePrompt);
        
        $descriptionPrompt = "Write a full YouTube video description for a white noise video made specifically for babies. The video is {$videoMinutes} minutes long and designed to help newborns, infants, or toddlers fall asleep faster, sleep deeper, or calm down when crying. It may also help soothe colicky babies. The description should be 150–300 words long, written in natural and professional English, and fully optimized for YouTube SEO and search. Include high-traffic keywords. The tone should be calm, reassuring, and directed toward parents. Do not include emojis, timestamps, hashtags, or links. Return only the description.";
        
        $description = ShortVideoHelper::generateText($descriptionPrompt);
        $description = str_replace('*', '', $description);

        $fullDescription = "$description\n\n" .
            "Keywords:#babywhitenoise #colickybaby #calmingbabysounds #sleepaidforinfants #cryingbabyrelief #soothecryinginfant #relaxingwhitenoise #babysleepsounds #magicwhitenoise #babynaptime #sleepingbabynoise #infantsleep #baby #babywhitenoise #whitenoiseforbabiesblackscreen #relaxingwhitenoise #whitenoiseblackscreen #whitenoiseforbabies #whitenoise #babysleepmusic #lullaby #lullabymusic\n\n" .
            "white noise for babies,for babies,white noise,baby white noise,white noise baby,white noise for sleeping,sleep,sleep sounds,womb sounds,colic,baby colic,colicky baby,baby,soothe,soothing,dark screen,black screen,baby sleeping,infant,crying,insomnia,bedtime,newborn,parent,fall asleep,mom,dad,kid,parenting,calm,baby sleep,naptime,baby crying,soothe crying baby";

        $snippet->setTitle($title);
        $snippet->setDescription($fullDescription);
        $snippet->setTags([
            "infant sleep sound",
            "sleep sound",
            "infant",
            "happy baby",
            "white noise",
            "pink noise",
            "brown noise",
            "white noise for babies",
            "white noise sleep",
            "sleep",
            "baby",
            "baby sleep",
            "white noise for sleeping",
            "colic",
            "crying",
            "baby crying",
            "put baby to sleep",
            "parent",
            "babies",
            "sleeping",
            "mom",
            "sleep noise",
            "soothing",
            "dad",
            "mother",
            "calm",
            "fall asleep",
            "sleep sounds",
            "womb",
            "colicky",
            "child",
            "sleep aid",
            "baby colic",
            "for sleep",
            "short video",
            "white noise baby",
            "baby white noise",
            "colicky baby",
            "sleeps",
            "white noise sound",
            "infant sleep"
        ]);

        $snippet->setCategoryId(24);

        // Set video status
        $status = new YouTube\VideoStatus();
        $status->setPrivacyStatus('public');

        // Create YouTube video object
        $video = new YouTube\Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Upload the video
        $chunkSizeBytes = 1 * 1024 * 1024; // 1MB
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
        Log::info('Short video upload finished');
        
        $this->uploadThumbnail($uploadStatus['id']);
        
        // Add to all playlists
        \App\Helpers\YouTubePlaylistHelper::addVideoToAllPlaylists($this->client, $uploadStatus['id']);

        return response()->json(['message' => 'Short video uploaded successfully', 'video_id' => $uploadStatus['id'], 'duration_minutes' => $videoMinutes]);
    }

    /**
     * Upload custom thumbnail for the video
     */
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

        $thumbnailPath = storage_path('app/public/merged_image_short.png');

        $response = $youtube->thumbnails->set(
            $id,
            [
                'data' => file_get_contents($thumbnailPath),
                'mimeType' => 'image/jpeg',
                'uploadType' => 'multipart',
            ]
        );

        return response()->json(['message' => 'Thumbnail uploaded successfully', 'response' => $response]);
    }

    /**
     * Refresh access token if expired
     */
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
