<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadVideoJob;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\YouTube;
use App\Models\access_token;

class youtubeController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/google_credentials.json')); // Path to your credentials file
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->addScope(YouTube::YOUTUBE); // Scope for managing YouTube videos
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    
public function refresh_token()
{

         $channelId = 2; // Get from request
        $accessTokenModel = access_token::where('channel_id', $channelId)->first();
        // dd($accessTokenModel->access_token, $accessTokenModel->refresh_token, $accessTokenModel->expires_at);

    $url = 'https://www.googleapis.com/oauth2/v4/token';

    $postData = [
        'client_id' => '1028727132250-br4224ahvrufk878k3dm64snj0h7mh9e.apps.googleusercontent.com',
        'client_secret' => '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',
        'refresh_token' => $accessTokenModel->refresh_token ,
        'grant_type' => 'refresh_token',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        return json_decode($response, true);
    } else {
        return [
            'error' => 'Failed to refresh token',
            'response' => $response,
            'http_code' => $httpCode,
        ];
    }
}

    // public function refresh_token(Request $request){
    //     $channelId = $request->input('channel_id'); // Get from request
    //     $accessTokenModel = access_token::where('channel_id', $channelId)->first();

    //     if (!$accessTokenModel) {
    //         return response()->json(['error' => 'Access token not found for channel ' . $channelId], 404);
    //     }

    //     $this->client->setAccessToken([
    //         'access_token' => $accessTokenModel->access_token,
    //         'refresh_token' => $accessTokenModel->refresh_token,
    //         'expires_in' => $accessTokenModel->expires_at ? $accessTokenModel->expires_at : null,
    //     ]);

    //     if ($this->client->isAccessTokenExpired()) {
    //         // Refresh the token if expired
    //         $this->client->fetchAccessTokenWithRefreshToken($accessTokenModel->refresh_token);
    //         $newAccessToken = $this->client->getAccessToken();
                
    //         $accessTokenModel->access_token = $newAccessToken['access_token'];
    //         $accessTokenModel->refresh_token = $newAccessToken['refresh_token'] ?? $accessTokenModel->refresh_token;
    //         $accessTokenModel->expires_at = $newAccessToken['expires_in'];
    //         $accessTokenModel->save();
    //         dd('done');
       
    //     }

    //     return response()->json(['message' => 'Access token refreshed successfully for channel ' . $channelId]);
    // }

     public function getAuthUrl(Request $request)
    {
            $channelId = $request->input('channel_id'); // Get from request
            $this->client->setAuthConfig(storage_path('app/google_credentials.json'));
            $this->client->setState($channelId); // <-- Set state here!
            $authUrl = $this->client->createAuthUrl();

            return response()->json(['auth_url' => $authUrl]);
    }
   

    // Step 2: Handle OAuth Callback and Get Access Token
    public function handleCallback(Request $request)
    {
       if ($request->has('code')) {
        $channelId = $request->input('state'); // Google returns 'state' param

        $this->client->fetchAccessTokenWithAuthCode($request->input('code'));
        $accessToken = $this->client->getAccessToken();

        // Save/update the access token for this channel
        $accessTokenModel = access_token::where('channel_id', $channelId)->first();
        if (!$accessTokenModel) {
            $accessTokenModel = new access_token();
            $accessTokenModel->channel_id = $channelId;
        }
        $accessTokenModel->access_token = $accessToken['access_token'];
        $accessTokenModel->refresh_token = $accessToken['refresh_token'] ;
        $accessTokenModel->expires_at = $accessToken['expires_in'];
        $accessTokenModel->scope = $accessToken['scope'] ;
        $accessTokenModel->created = $accessToken['created'] ;
        $accessTokenModel->token_type = $accessToken['token_type'] ;
        // $accessTokenModel->refresh_token_expires_in = $accessToken['refresh_token_expires_in'];
        
        $accessTokenModel->save();

        return response()->json(['message' => 'Access token saved successfully for channel ' . $channelId]);
         }

         return response()->json(['error' => 'Authorization code not provided'], 400);
    }

    public function refresh_access_token(Request $request)
    {
        $channelId = $request->input('channel_id'); // Get from request
        $accessTokenModel = access_token::where('channel_id', $channelId)->first();

        if (!$accessTokenModel) {
            return response()->json(['error' => 'Access token not found for channel ' . $channelId], 404);
        }

        $this->client->setAccessToken([
            'access_token' => $accessTokenModel->access_token,
            'refresh_token' => $accessTokenModel->refresh_token,
            'expires_in' => $accessTokenModel->expires_at ? $accessTokenModel->expires_at->diffInSeconds(now()) : null,
        ]);

        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if expired
            $this->client->fetchAccessTokenWithRefreshToken($accessTokenModel->refresh_token);
            $newAccessToken = $this->client->getAccessToken();
            // Update the access token in the database
            $accessTokenModel->access_token = $newAccessToken['access_token'];
            $accessTokenModel->refresh_token = $newAccessToken['refresh_token'] ?? $accessTokenModel->refresh_token;
            $accessTokenModel->expires_at = now()->addSeconds($newAccessToken['expires_in']);
            $accessTokenModel->save();
        }

        return response()->json(['message' => 'Access token refreshed successfully for channel ' . $channelId]);
    }
    public function handleCallback1(Request $request)
    {
        if ($request->has('code')) {
            $this->client->fetchAccessTokenWithAuthCode($request->input('code'));
            $accessToken = $this->client->getAccessToken();
            // Save the access token to storage for future use
            file_put_contents(storage_path('app/google_access_token1.json'), json_encode($accessToken));
            return response()->json(['message' => 'Access token saved successfully']);
        }

        return response()->json(['error' => 'Authorization code not provided'], 400);
    }
    public function handleCallback0(Request $request)
    {
        if ($request->has('code')) {
            $this->client->fetchAccessTokenWithAuthCode($request->input('code'));
            $accessToken = $this->client->getAccessToken();
            // Save the access token to storage for future use
            file_put_contents(storage_path('app/google_access_token0.json'), json_encode($accessToken));
            return response()->json(['message' => 'Access token saved successfully']);
        }

        return response()->json(['error' => 'Authorization code not provided'], 400);
    }

    // Step 3: Upload Video to YouTube
    public function uploadVideo(Request $request)
    {
        UploadVideoJob::dispatch();
        dd('done');
        ini_set('max_execution_time', 3600); // 3600 seconds = 60 minutes
        set_time_limit(3600);
        // Load the saved access token
        $accessToken = json_decode(file_get_contents(storage_path('app/google_access_token.json')), true);
        $this->client->setAccessToken($accessToken);


        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if expired
            $refreshToken = $accessToken['refresh_token'];
            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents(storage_path('app/google_access_token.json'), json_encode($this->client->getAccessToken()));
        }

        $youtube = new YouTube($this->client);

        // Validate request
        // $request->validate([
        //     'title' => 'required|string',
        //     'description' => 'required|string',
        //     'video' => 'required|file|mimes:mp4,mov,avi|max:102400', // Max 100MB
        // ]);

        // $videoPath = $request->file('video')->getPathname();
        $videoPath = storage_path('app/videos/video.mp4');


        // Create a snippet with title and description
        $snippet = new YouTube\VideoSnippet();
        $snippet->setTitle('My video title');
        $snippet->setDescription('This is a sample video description');
        $snippet->setTags(['example', 'youtube', 'api']);
        // $snippet->setCategoryId(22);
        // $snippet->setThumbnails();

        // Set video status
        $status = new YouTube\VideoStatus();
        $status->setPrivacyStatus('public'); // Options: public, private, unlisted

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
        $this->uploadThumbnail($status['id']);

        return response()->json(['message' => 'Video uploaded successfully', 'video_id' => $status['id']]);
    }

    public function uploadThumbnail($id)
    {


        // Load the saved access token
        $accessToken = json_decode(file_get_contents(storage_path('app/google_access_token.json')), true);
        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if expired
            $refreshToken = $accessToken['refresh_token'];
            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents(storage_path('app/google_access_token.json'), json_encode($this->client->getAccessToken()));
        }

        $youtube = new YouTube($this->client);

        // Get the thumbnail file path
        $thumbnailPath = storage_path('app/thumbnails/thumbnail.png');

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


    //  $status->setSelfDeclaredMadeForKids(false);
    public function liveStreem2()
    {
        // Load the saved access token
        $accessToken = json_decode(file_get_contents(storage_path('app/google_access_token.json')), true);
        $this->client->setAccessToken($accessToken);
    
        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if expired
            $refreshToken = $accessToken['refresh_token'];
            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents(storage_path('app/google_access_token.json'), json_encode($this->client->getAccessToken()));
        }
    
        $youtube = new YouTube($this->client);
    
        // Step 1: Create a live broadcast
        $broadcastSnippet = new YouTube\LiveBroadcastSnippet();
        $broadcastSnippet->setTitle('My Live Stream');
        $broadcastSnippet->setDescription('Streaming a video file to YouTube Live');
        $broadcastSnippet->setScheduledStartTime(now()->addMinutes(1)->toIso8601String()); // Start in 1 minute
        $broadcastSnippet->setScheduledEndTime(now()->addHours(2)->toIso8601String()); // End in 2 hours
    
        $status = new YouTube\LiveBroadcastStatus();
        $status->setPrivacyStatus('public'); // Options: public, private, unlisted
        $status->setSelfDeclaredMadeForKids(false);
    
        $broadcast = new YouTube\LiveBroadcast();
        $broadcast->setSnippet($broadcastSnippet);
        $broadcast->setStatus($status);
        $broadcast->setKind('youtube#liveBroadcast');
    
        $broadcastResponse = $youtube->liveBroadcasts->insert('snippet,status', $broadcast);
    
        // Step 2: Create a live stream
        $streamSnippet = new YouTube\LiveStreamSnippet();
        $streamSnippet->setTitle('My Live Stream');
    
        // Correctly initialize the CdnSettings object
        $cdn = new \Google\Service\YouTube\CdnSettings();
        $cdn->setFormat('1080p'); // Set the resolution (e.g., 1080p, 720p, etc.)
        $cdn->setIngestionType('rtmp'); // Set the ingestion type
        $cdn->setResolution('1080p'); // Set the resolution
        $cdn->setFrameRate('60fps'); // Set the frame rate
    
        $stream = new YouTube\LiveStream();
        $stream->setSnippet($streamSnippet);
        $stream->setCdn($cdn);
        $stream->setKind('youtube#liveStream');
    
        $streamResponse = $youtube->liveStreams->insert('snippet,cdn', $stream);
    
        // Step 3: Bind the live broadcast to the live stream
        $youtube->liveBroadcasts->bind(
            $broadcastResponse['id'],
            'id,contentDetails',
            ['streamId' => $streamResponse['id']]
        );
    
        // Step 4: Start streaming the video file using FFmpeg
        $videoPath = storage_path('app/videos/video.mp4'); // Path to your video file
        $streamUrl = $streamResponse['cdn']['ingestionInfo']['ingestionAddress'];
        $streamKey = $streamResponse['cdn']['ingestionInfo']['streamName'];
    
        $fullStreamUrl = $streamUrl . '/' . $streamKey;
    
        // Use FFmpeg to stream the video
        $command = "ffmpeg -re -i {$videoPath} -c:v libx264 -preset veryfast -maxrate 3000k -bufsize 6000k -pix_fmt yuv420p -g 50 -c:a aac -b:a 128k -ar 44100 -f flv {$fullStreamUrl}";
        shell_exec($command);
    dd('here');
        // Step 5: Transition the broadcast to "live" status
        sleep(10); // Wait for FFmpeg to start sending data
        $youtube->liveBroadcasts->transition(
            'live', // Transition to "live" status
            $broadcastResponse['id'],
            'status'
        );
    
        return response()->json([
            'message' => 'Live stream started successfully',
            'broadcast_id' => $broadcastResponse['id'],
            'stream_id' => $streamResponse['id'],
            'stream_url' => $streamUrl,
            'stream_key' => $streamKey,
        ]);
    }
    // public function liveStreem2()
    // {
    //     // return $videoPath = storage_path('app/videos/video.mp4');
    //     // Load the saved access token
    //     $accessToken = json_decode(file_get_contents(storage_path('app/google_access_token.json')), true);
    //     $this->client->setAccessToken($accessToken);

    //     if ($this->client->isAccessTokenExpired()) {
    //         // Refresh the token if expired
    //         $refreshToken = $accessToken['refresh_token'];
    //         $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
    //         file_put_contents(storage_path('app/google_access_token.json'), json_encode($this->client->getAccessToken()));
    //     }

    //     $youtube = new YouTube($this->client);

    //     // Step 1: Create a live broadcast
    //     $broadcastSnippet = new YouTube\LiveBroadcastSnippet();
    //     $broadcastSnippet->setTitle('My Live Stream');
    //     $broadcastSnippet->setDescription('Streaming a video file to YouTube Live');
    //     $broadcastSnippet->setScheduledStartTime(now()->addMinutes(1)->toIso8601String()); // Start in 5 minutes
    //     $broadcastSnippet->setScheduledEndTime(now()->addHours(2)->toIso8601String()); // End in 2 hours
        

    //     $status = new YouTube\LiveBroadcastStatus();
    //     $status->setPrivacyStatus('public'); // Options: public, private, 
    //     $status->setSelfDeclaredMadeForKids(false);
    //     $status->setLifeCycleStatus('live');
     

    //     $broadcast = new YouTube\LiveBroadcast();
    //     $broadcast->setSnippet($broadcastSnippet);
    //     $broadcast->setStatus($status);
        
    //     $broadcast->setKind('youtube#liveBroadcast');

    //     $broadcastResponse = $youtube->liveBroadcasts->insert('snippet,status', $broadcast);

    //     // Step 2: Create a live stream
    //     $streamSnippet = new YouTube\LiveStreamSnippet();
    //     $streamSnippet->setTitle('My Live Stream');

    //     // Correctly initialize the CdnSettings object
    //     $cdn = new \Google\Service\YouTube\CdnSettings();
    //     $cdn->setFormat('1080p'); // Set the resolution (e.g., 1080p, 720p, etc.)
    //     $cdn->setIngestionType('rtmp'); // Set the ingestion type
    //     $cdn->setResolution('1080p'); // Set the ingestion type
    //     $cdn->setFrameRate('60fps'); // Set the ingestion type

    //     $stream = new YouTube\LiveStream();
    //     $stream->setSnippet($streamSnippet);
    //     $stream->setCdn($cdn);
    //     $stream->setKind('youtube#liveStream');

    //     $streamResponse = $youtube->liveStreams->insert('snippet,cdn', $stream);

    //     // Step 3: Bind the live broadcast to the live stream
    //     $youtube->liveBroadcasts->bind(
    //         $broadcastResponse['id'],
    //         'id,contentDetails',
    //         ['streamId' => $streamResponse['id']]
    //     );

    //     // Step 4: Stream the video file using FFmpeg
    //     $videoPath = storage_path('app/videos/video.mp4'); // Path to your video file
    //     $streamUrl = $streamResponse['cdn']['ingestionInfo']['ingestionAddress'];
    //     $streamKey = $streamResponse['cdn']['ingestionInfo']['streamName'];

    //     $fullStreamUrl = $streamUrl . '/' . $streamKey;

    //     // Use FFmpeg to stream the video
    //     $command = "ffmpeg -re -i {$videoPath} -c:v libx264 -preset veryfast -maxrate 3000k -bufsize 6000k -pix_fmt yuv420p -g 50 -c:a aac -b:a 128k -ar 44100 -f flv {$fullStreamUrl}";

    //     shell_exec($command);


    //     return response()->json([
    //         'message' => 'Live stream started successfully',
    //         'broadcast_id' => $broadcastResponse['id'],
    //         'stream_id' => $streamResponse['id'],
    //         'stream_url' => $streamUrl,
    //         'stream_key' => $streamKey,
    //     ]);
    // }

   
    // public function liveStreem2()
    // {
    //     // Load the saved access token
    //     $accessToken = json_decode(file_get_contents(storage_path('app/google_access_token.json')), true);
    //     $this->client->setAccessToken($accessToken);

    //     if ($this->client->isAccessTokenExpired()) {
    //         // Refresh the token if expired
    //         $refreshToken = $accessToken['refresh_token'];
    //         $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
    //         file_put_contents(storage_path('app/google_access_token.json'), json_encode($this->client->getAccessToken()));
    //     }

    //     $youtube = new YouTube($this->client);

    //     // Step 1: Fetch a video from your YouTube channel using the search API
    //     $videos = $youtube->search->listSearch('snippet', [
    //         'forMine' => true,
    //         'type' => 'video',
    //         'maxResults' => 1, // Fetch the latest video
    //     ]);

    //     if (empty($videos['items'])) {
    //         return response()->json(['error' => 'No videos found on your channel'], 404);
    //     }

    //     $videoId = $videos['items'][0]['id']['videoId']; // Get the videoId from the id object
    //     $videoTitle = $videos['items'][0]['snippet']['title'];

    //     // Step 2: Create a live broadcast
    //     $broadcastSnippet = new YouTube\LiveBroadcastSnippet();
    //     $broadcastSnippet->setTitle('Live Stream: ' . $videoTitle);
    //     $broadcastSnippet->setDescription('Streaming video from my channel: ' . $videoTitle);
    //     $broadcastSnippet->setScheduledStartTime(now()->addMinutes(1)->toIso8601String()); // Start in 1 minute
    //     $broadcastSnippet->setScheduledEndTime(now()->addHours(2)->toIso8601String()); // End in 2 hours

    //     $status = new YouTube\LiveBroadcastStatus();
    //     $status->setPrivacyStatus('public'); // Options: public, private, unlisted

    //     $broadcast = new YouTube\LiveBroadcast();
    //     $broadcast->setSnippet($broadcastSnippet);
    //     $broadcast->setStatus($status);
    //     $broadcast->setKind('youtube#liveBroadcast');

    //     $broadcastResponse = $youtube->liveBroadcasts->insert('snippet,status', $broadcast);

    //     // Step 3: Create a live stream
    //     $streamSnippet = new YouTube\LiveStreamSnippet();
    //     $streamSnippet->setTitle('Live Stream for: ' . $videoTitle);

    //     $cdn = new YouTube\CdnSettings();
    //     $cdn->setFormat('1080p'); // Set the resolution
    //     $cdn->setIngestionType('rtmp'); // Set the ingestion type
    //     $cdn->setResolution('1080p'); // Set the ingestion type
    //     $cdn->setFrameRate('60fps'); // Set the ingestion type


    //     $stream = new YouTube\LiveStream();
    //     $stream->setSnippet($streamSnippet);
    //     $stream->setCdn($cdn);
    //     $stream->setKind('youtube#liveStream');

    //     $streamResponse = $youtube->liveStreams->insert('snippet,cdn', $stream);

    //     // Step 4: Bind the live stream to the live broadcast
    //     $youtube->liveBroadcasts->bind(
    //         $broadcastResponse['id'],
    //         'id,contentDetails',
    //         ['streamId' => $streamResponse['id']] // Use the live stream ID
    //     );

    //     return response()->json([
    //         'message' => 'Live stream created successfully',
    //         'broadcast_id' => $broadcastResponse['id'],
    //         'stream_id' => $streamResponse['id'],
    //         'video_id' => $videoId,
    //         'video_title' => $videoTitle,
    //     ]);
    // }


    // public function liveStreem2()
    // {
    //     // Load the saved access token
    //     $accessToken = json_decode(file_get_contents(storage_path('app/google_access_token.json')), true);
    //     $this->client->setAccessToken($accessToken);

    //     if ($this->client->isAccessTokenExpired()) {
    //         // Refresh the token if expired
    //         $refreshToken = $accessToken['refresh_token'];
    //         $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
    //         file_put_contents(storage_path('app/google_access_token.json'), json_encode($this->client->getAccessToken()));
    //     }

    //     $youtube = new YouTube($this->client);

    //     // Step 1: Fetch a video from your YouTube channel using the search API
    //     $videos = $youtube->search->listSearch('snippet', [
    //         'forMine' => true,
    //         'type' => 'video',
    //         'maxResults' => 1, // Fetch the latest video
    //     ]);

    //     if (empty($videos['items'])) {
    //         return response()->json(['error' => 'No videos found on your channel'], 404);
    //     }

    //     $videoId = $videos['items'][0]['id']; // Get the ID of the first video
    //     $videoTitle = $videos['items'][0]['snippet']['title'];

    //     // Step 2: Create a live broadcast
    //     $broadcastSnippet = new YouTube\LiveBroadcastSnippet();
    //     $broadcastSnippet->setTitle('Live Stream: ' . $videoTitle);
    //     $broadcastSnippet->setDescription('Streaming video from my channel: ' . $videoTitle);
    //     $broadcastSnippet->setScheduledStartTime(now()->addMinutes(1)->toIso8601String()); // Start in 1 minute
    //     $broadcastSnippet->setScheduledEndTime(now()->addHours(2)->toIso8601String()); // End in 2 hours

    //     $status = new YouTube\LiveBroadcastStatus();
    //     $status->setPrivacyStatus('public'); // Options: public, private, unlisted

    //     $broadcast = new YouTube\LiveBroadcast();
    //     $broadcast->setSnippet($broadcastSnippet);
    //     $broadcast->setStatus($status);
    //     $broadcast->setKind('youtube#liveBroadcast');

    //     $broadcastResponse = $youtube->liveBroadcasts->insert('snippet,status', $broadcast);

    //     // Step 3: Bind the video as an asset to the live broadcast
    //     $youtube->liveBroadcasts->bind(
    //         $broadcastResponse['id'],
    //         'id,contentDetails',
    //         ['streamId' => $videoId] // Bind the video as the stream
    //     );

    //     return response()->json([
    //         'message' => 'Live stream created successfully',
    //         'broadcast_id' => $broadcastResponse['id'],
    //         'video_id' => $videoId,
    //         'video_title' => $videoTitle,
    //     ]);
    // }
    
      public function repeatVideoToFiveMinutes(Request $request)
{
    // Validate the uploaded video
    $request->validate([
        'video' => 'required|file|mimetypes:video/mp4,video/avi,video/mpeg,video/quicktime|max:51200', // max 50MB
    ]);

    $videoPath = $request->file('video')->getRealPath();
    $outputPath = storage_path('app/public/repeated_video.mp4');
   

    // Get the duration of the input video
    $ffprobe = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"$videoPath\"");
    $duration = floatval($ffprobe);
    dd($ffprobe);

    if ($duration <= 0) {
        return response()->json(['error' => 'Could not get video duration.'], 400);
    }

    // Calculate how many times to loop the video
    $loops = ceil(300 / $duration); // 300 seconds = 5 minutes

    // Create a temporary text file listing the video multiple times
    $listPath = storage_path('app/public/video_list.txt');
    $listContent = '';
    for ($i = 0; $i < $loops; $i++) {
        $listContent .= "file '$videoPath'\n";
    }
    file_put_contents($listPath, $listContent);

    // Concatenate the video files
    $command = "ffmpeg -y -f concat -safe 0 -i \"$listPath\" -c copy \"$outputPath\"";
    shell_exec($command);

    // Trim the output video to exactly 5 minutes
    $finalOutputPath = storage_path('app/public/repeated_video_5min.mp4');
    $trimCommand = "ffmpeg -y -i \"$outputPath\" -t 300 -c copy \"$finalOutputPath\"";
    shell_exec($trimCommand);

    // Clean up
    @unlink($listPath);
    @unlink($outputPath);

    return response()->json([
        'message' => 'Video repeated to 5 minutes successfully.',
        'path' => $finalOutputPath,
    ]);
}
}
