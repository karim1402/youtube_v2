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
use App\Helpers\GeminiHelper;
use App\Models\access_token;
use App\Jobs\UploadVideoPureJob;


class UploadVideoJobtest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $videoPath;
    private $title;
    private $description;

    private $client;
    public $timeout = 3600;

    /**
     * Create a new job instance.
     *
     * @param string $videoPath
     * @param string $title
     * @param string $description
     */
    public function __construct() {}
    /**
     * Execute the job.
     */
    public function handle()
    {

        

        //  Log::info('video started to create');

         GeminiHelper::runvideo();

         return 0;

        //  Log::info('video created');

        //  Log::info('video upload started');





        $video_hours_length =  10;
       

        GeminiHelper::overlayImages();

        $channelId = '2';

        $this->client = new Client();

        $this->refresh_access_token($channelId);


        $this->client->setAuthConfig(storage_path('app/google_credentials.json')); // Path to your credentials file
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        ini_set('max_execution_time', 7200);
        set_time_limit(7200); //
        // Load the saved access token

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



        //random number from 1 to 4 
        // $randomNumber = 3;
        //rand(1, 6);


        $videoPath = storage_path("app/outputs/finaloutpt123.mp4");

        //   Log::info('UploadVideoJob started 3');
        // Create a snippet with title and description
        $snippet = new YouTube\VideoSnippet();
        $title = "this is list of my youtube video its video about white noise for baby make me titel to get most views based on youtube algrtiom The title must be under 100 characters, fully optimized for YouTube SEO, and written in clear, fluent English 
            Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound, fall asleep fast), and mirror the successful rhythm and phrasing of the examples above. You may use pipes | or dashes - to separate parts naturally, but avoid emojis, clickbait, or artificial language.
            Return only the title — no commentary or explanation. Return only the final title — no extra explanation.
            the video length is $video_hours_length hours
            1-White Noise for Babies - Soothe Your Fussy Baby to Sleep Fast with 10 Hours of Relaxing Sounds
            2-White Noise for Babies | Comfort Your Crying Newborn with This 10-Hour Calming Sleep Sound
            3-Colicky Baby Sleeps Soundly Tonight | 10 Hours White Noise for Babies | No Ads Peaceful Sound
            4-Crying Infant Sleeps with White Noise | Magic Sound for Babies | Colicky Baby Sleep Solution
            5-White Noise for Babies | Colicky Baby Sleeps Like Magic | 10 Hours of Peaceful Sleeping Sound
            6-Colicky Baby Sleeps with Soothing Magic | White Noise for Babies | 10 Hours Without Disruption
            7-Magic Sound for Colicky Baby | White Noise for Babies to Help Infant Sleep Through the Night
            8-Baby White Noise Fan Sounds - White Noise Sound to Help You Fall Asleep
            9-White Noise for Babies - 10 Hours of Gentle Sound to Calm Colicky Baby & Help Them Sleep
            10-White Noise for Babies - Magic Sound for Baby Sleep - White Noise 10 Hours
            11-White Noise - Transform Your Babys Sleep with White Noise for Babies
            12-White Noise for Babies | 10 Hours of Soothing Sound to Help Colicky Baby Sleep Instantly
            13-10 Hours of White Noise for Baby Sleep | Calm Crying Infants and Ensure a Peaceful Night
            14-Colicky Baby Sleeps To This Magic Sound 🍼 White Noise for Babies 10 Hours ❤️ Soothe crying infant
            15-Put a Baby to Sleep the Whole Night  🌙 10 Hours Magic Sounds to Help Soothe Colicky Infants Sleep
            16-White Noise for Baby Sleep 👶 | Instantly Calm Crying & Sleep Soundly ✨
            17-Colicky Baby Sleeps To This Magic Sound | White Noise 10 Hours | Soothe crying infant
            18-White Noise For Babies - 10 Hours Magic Sounds to Help Soothe Colicky Infants and Better Sleep
            19-White Noise for Babies - Magic Sound for Baby Sleep - White Noise 10 Hours
            20-Colicky Baby Sleeps To This Magic Sound | White Noise 10 Hours | Soothe crying infant
            21-White Noise for Crying Infant | 10 Hours of Magic Sound to Help Colicky Baby Sleep
            22-White Noise for Babies | Colicky Baby Sleeps to This Magic Sound | 10 Hours Soothing Baby Sleep Aid
            23-White Noise For Babies - 10 Hours of Gentle Sounds to Help Fussy Infants Sleep Better
            24-10 Hours of White Noise for Infants | Nonstop Gentle Sound for a Comfortable and Restful Sleep
            25-White Noise to Instantly Calm Crying Babies | Sleep Aid for Colicky Infants
            27-White Noise for Babies - 10 Hours of Peaceful Sound to Help Colicky Infants Fall Asleep Fast
            28-White Noise for Babies to Sleep Instantly | 10 Hours of Relaxing Sounds for Crying Infants
            29-White Noise For Babies | Calming Sleep Aid to Soothe Colicky Infants and Relieve Irritability


            ";
        //         'Write one unique YouTube video title for a white noise video designed to help babies sleep. Use the same tone, structure, and emotional appeal as high-performing titles like:

        // • Colicky Baby Sleeps To This Magic Sound | White Noise 10 Hours | Soothe Crying Infant
        // • White Noise for Babies – 10 Hours of Peaceful Sound to Help Colicky Infants Fall Asleep Fast
        // • White Noise for Crying Infant | 10 Hours of Magic Sound to Help Colicky Baby Sleep

        // The title must be under 100 characters, fully optimized for YouTube SEO, and written in clear, fluent English.

        // Focus on key themes such as: white noise, colic relief, crying infants, instant sleep, gentle/soothing sounds, and 10 hours of uninterrupted calming audio.

        // Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound, fall asleep fast), and mirror the successful rhythm and phrasing of the examples above. You may use pipes | or dashes - to separate parts naturally, but avoid emojis, clickbait, or artificial language.

        // Return only the title — no commentary or explanation. Return only the final title — no extra explanation.';
        //  "
        // this is list of titels thats get good views can you write titel for my youtube chanel with the same of this list to get most views based on youtube algrtiom giv me just the titel in replay message with Maximum 100 characters 

        // 1-White Noise for Babies - Soothe Your Fussy Baby to Sleep Fast with 10 Hours of Relaxing Sounds
        // 2-White Noise for Babies | Comfort Your Crying Newborn with This 10-Hour Calming Sleep Sound
        // 3-Colicky Baby Sleeps Soundly Tonight | 10 Hours White Noise for Babies | No Ads Peaceful Sound
        // 4-Crying Infant Sleeps with White Noise | Magic Sound for Babies | Colicky Baby Sleep Solution
        // 5-White Noise for Babies | Colicky Baby Sleeps Like Magic | 10 Hours of Peaceful Sleeping Sound
        // 6-Colicky Baby Sleeps with Soothing Magic | White Noise for Babies | 10 Hours Without Disruption
        // 7-Magic Sound for Colicky Baby | White Noise for Babies to Help Infant Sleep Through the Night
        // 8-Baby White Noise Fan Sounds - White Noise Sound to Help You Fall Asleep
        // 9-White Noise for Babies - 10 Hours of Gentle Sound to Calm Colicky Baby & Help Them Sleep
        // 10-White Noise for Babies - Magic Sound for Baby Sleep - White Noise 10 Hours
        // 11-White Noise - Transform Your Baby's Sleep with White Noise for Babies
        // 12-White Noise for Babies | 10 Hours of Soothing Sound to Help Colicky Baby Sleep Instantly
        // 13-10 Hours of White Noise for Baby Sleep | Calm Crying Infants and Ensure a Peaceful Night
        // 14-Colicky Baby Sleeps To This Magic Sound 🍼 White Noise for Babies 10 Hours ❤️ Soothe crying infant
        // 15-Put a Baby to Sleep the Whole Night  🌙 10 Hours Magic Sounds to Help Soothe Colicky Infants Sleep
        // 16-White Noise for Baby Sleep 👶 | Instantly Calm Crying & Sleep Soundly ✨
        // ";


        $title = GeminiHelper::base($title);
        $description = GeminiHelper::base("Write a full YouTube video description for a white noise video made specifically for babies. The video is designed to help newborns, infants, or toddlers fall asleep faster, sleep deeper, or calm down when crying. It may also help soothe colicky babies. The description should be 150–300 words long, written in natural and professional English, and fully optimized for YouTube SEO and search. It should include high-traffic keywords such as: 'white noise for babies', 'baby sleep sounds', 'soothing baby sleep', 'help baby fall asleep', 'colic relief', 'infant sleep aid', 'baby white noise 10 hours', 'no ads baby sleep sounds', 'sleep music for newborns', 'calm baby crying', etc. Explain briefly the benefits of white noise for babies, how the video can be used during naps or nighttime, and highlight that it plays continuously for 10 hours without interruptions or ads. The tone should be calm, reassuring, and directed toward parents looking for safe and effective sleep solutions for their babies. Do not include emojis, timestamps, hashtags, or links. Return only the description — no headers, no titles, and no extras.");

        $description = str_replace('*', '', $description);
        $start =   "$description" . "
       " .

            "Keywords:#babywhitenoise #colickybaby #calmingbabysounds #sleepaidforinfants #cryingbabyrelief #soothecryinginfant #relaxingwhitenoise #babysleepsounds #magicwhitenoise #babynaptime #sleepingbabynoise #infantsleep #baby #babywhitenoise #whitenoiseforbabiesblackscreen #relaxingwhitenoise #relaxingwhitenoise #whitenoiseblackscreen #whitenoiseforbabies #whitenoise #babysleepmusic #lullaby #lullabymusic #babysleepmusic#babysleepmusic #lullaby #lullabymusic #babysleepmusic#lullabymusic #babysleepmusic #lullaby #babywhitenoise #colickybaby #calmingbabysounds #sleepaidforinfants #cryingbabyrelief #soothecryinginfant #relaxingwhitenoise #babysleepsounds #magicwhitenoise #babynaptime #sleepingbabynoise #infantsleep #baby #babywhitenoise #whitenoiseforbabiesblackscreen #relaxingwhitenoise #relaxingwhitenoise" . "
        "
                    . "white noise for babies,for babies,white noise,baby white noise,white noise baby,white noise for sleeping,sleep,sleep sounds,womb sounds,colic,baby colic,colicky baby,baby,soothe,soothing,dark screen,black screen,baby sleeping,infant,Baby Colic (Symptom),Crying (Symptom),Sleeping,cry,crying,insomnia,bedtime,irritable,newborn,parent,fall asleep,mom,dad,kid,tantrum,parenting,calm,baby sleep,dormir,naptime,baby crying,soothe crying baby,mother
        white noise,black screen,brown noise,dark screen,sound machine,white noise machine,sound masking,sleep machine,no image,fan noise,fan,sleep sounds,sleep,sleep aid,10 hours,dormir,relaxing white noise,study,study aid,study sound,noise,white noise sleep,white noise for sleeping,white noise for studying,sleep noise,sleeping sounds,sounds for sleeping,focus,white noise for sleep,white noise to sleep,fade to black,black screen white noise
        white noise,pink noise,brown noise,sleep,baby,baby sleep,colic,crying,baby crying,put baby to sleep,infant,boy,girl,cry,bawling,fussy,insomnia,night,bedtime,irritable,newborn,parent,sleeping,mom,dad,mother,father,kid,tantrum,tip,how to,calm,mommy,fall asleep,sleepy,baby sleep music,sleep sounds,stay asleep,womb,colicky,womb sounds,child,family,mama,daughter,sleep aid,nap time,dormir,schlafen,baby colic,parenting advice,son,nap,naptime";

        $snippet->setTitle($title);
        $snippet->setDescription($start);
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
            "10 hours",
            "white noise baby",
            "baby white noise",
            "colicky baby",
            "sleeps",
            "white noise sound",
            "infant sleep"
        ]);

        $snippet->setCategoryId(24);
        // $snippet->setThumbnails();

        // Set video status
        $status = new YouTube\VideoStatus();
        $status->setPrivacyStatus('public'); // Options: public, private, unlisted
        //make the video premiere


        // Create a YouTube video object
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

        $status = false;
        $handle = fopen($videoPath, 'rb');
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }
        fclose($handle);

        $this->client->setDefer(false);
        Log::info('video upload finished');
        $this->uploadThumbnail($status['id']);

        return response()->json(['message' => 'Video uploaded successfully', 'video_id' => $status['id']]);
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

        // Get the thumbnail file path
        $thumbnailPath = storage_path('app/public/merged_image.png');

        // Upload the custom thumbnail
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


    public function refreshAccessToken()
    {
        // Load the saved access token
        $accessTokenPath = storage_path('app/google_access_token1.json');
        if (!file_exists($accessTokenPath)) {
            return response()->json(['error' => 'Access token file not found'], 404);
        }

        $accessToken = json_decode(file_get_contents($accessTokenPath), true);
        $this->client->setAccessToken($accessToken);

        // Check if the token is expired
        // if ($this->client->isAccessTokenExpired()) {
        if (isset($accessToken['refresh_token'])) {
            try {
                // Refresh the token
                $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);

                // Check for errors in the response
                if (isset($newAccessToken['error'])) {
                    return response()->json([
                        'error' => $newAccessToken['error'],
                        'error_description' => $newAccessToken['error_description'] ?? 'No description provided',
                    ], 400);
                }

                // Save the new access token
                file_put_contents($accessTokenPath, json_encode($this->client->getAccessToken()));

                return response()->json(['message' => 'Access token refreshed successfully']);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to refresh access token', 'details' => $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Refresh token not found in access token file'], 400);
        }
        // }

        // return response()->json(['message' => 'Access token is still valid']);
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
            // Refresh the token if expired
            $this->client->fetchAccessTokenWithRefreshToken($accessTokenModel->refresh_token);
            $newAccessToken = $this->client->getAccessToken();
            // Update the access token in the database
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
