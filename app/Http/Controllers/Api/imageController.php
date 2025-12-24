<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class imageController extends Controller
{

 public function overlayImages1(Request $request)
{
    // Randomly select background and baby images
    $back = rand(1, 8);
    $baby = rand(1, 11);

    // Define paths
    $baseImagePath = storage_path("app/background1/$back.png");
    $overlayImagePath = storage_path("app/baby1/$baby.png");
    $cornerImagePath = storage_path("app/logo/file.png");

    // Create image resources
    $baseImageOriginal = imagecreatefromstring(file_get_contents($baseImagePath));
    $overlayImage = imagecreatefromstring(file_get_contents($overlayImagePath));
    $cornerImage = imagecreatefromstring(file_get_contents($cornerImagePath));

    // Get dimensions of the overlay image (this will be final image size)
    $overlayWidth = imagesx($overlayImage);
    $overlayHeight = imagesy($overlayImage);

    // Resize base image to match overlay dimensions
    $baseImage = imagecreatetruecolor($overlayWidth, $overlayHeight);
    imagecopyresampled(
        $baseImage,
        $baseImageOriginal,
        0,
        0,
        0,
        0,
        $overlayWidth,
        $overlayHeight,
        imagesx($baseImageOriginal),
        imagesy($baseImageOriginal)
    );

    // Overlay the baby image on top of resized base image
    imagecopy($baseImage, $overlayImage, 0, 0, 0, 0, $overlayWidth, $overlayHeight);

    // Resize the corner logo (12% of overlay width)
    $cornerWidth = $overlayWidth * 0.12;
    $cornerHeight = $cornerWidth * (imagesy($cornerImage) / imagesx($cornerImage));
    $cornerResized = imagecreatetruecolor($cornerWidth, $cornerHeight);
    imagealphablending($cornerResized, false);
    imagesavealpha($cornerResized, true);
    imagecopyresampled(
        $cornerResized,
        $cornerImage,
        0,
        0,
        0,
        0,
        $cornerWidth,
        $cornerHeight,
        imagesx($cornerImage),
        imagesy($cornerImage)
    );

    // Position the logo in the top-right corner with 2% margin
    $marginRight = $overlayWidth * 0.02;
    $marginTop = $overlayHeight * 0.02;
    $positionX = $overlayWidth - $cornerWidth - $marginRight;
    $positionY = $marginTop;

    // Merge the corner logo onto the image
    imagecopy($baseImage, $cornerResized, $positionX, $positionY, 0, 0, $cornerWidth, $cornerHeight);

    // Save final image
    $outputPath = storage_path('app/public/test/merged_image1.png');
    imagepng($baseImage, $outputPath);

    // Clean up
    imagedestroy($baseImageOriginal);
    imagedestroy($baseImage);
    imagedestroy($overlayImage);
    imagedestroy($cornerImage);
    imagedestroy($cornerResized);

    return 'done kemo';
}

    public function fx(Request $request)
    {

        // $clientId = '1028727132250-br4224ahvrufk878k3dm64snj0h7mh9e.apps.googleusercontent.com';
        // $redirectUri = 'https://dvadsstage.devdigitalvibes.com/public/admin/login'; // e.g., http://127.0.0.1:8000/api/google/callback
        // $scopes = [
        //     'https://www.googleapis.com/auth/cloud-platform',
        // ];
    
        // $authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
        //     'client_id' => $clientId,
        //     'redirect_uri' => $redirectUri,
        //     'response_type' => 'code',
        //     'scope' => implode(' ', $scopes),
        //     'access_type' => 'offline',
        //     'prompt' => 'consent',
        // ]);
    
        // return response()->json(['auth_url' => $authUrl]);

        // $code = "4/0AUJR-x6QqPdqNBIsHM7OiBm0Gr1zeDXgw5dVudVP42Jo6Ibs_eC1QE1J5PQVScIJehYefQ";

        // $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
        //     'code' => $code,
        //     'client_id' => $clientId,
        //     'client_secret' => "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",
        //     'redirect_uri' => $redirectUri,
        //     'grant_type' => 'authorization_code',
        // ]);

        // return $response->json();

        //set_time_out unlimited
        set_time_limit(0);

        $token = "Bearer " . env('GOOGLE_OAUTH_TOKEN');
      //https://aisandbox-pa.googleapis.com/v1:runImageFx
      //   {"userInput":{"candidatesCount":4,"prompts":["A peaceful baby sleeping on a soft, crescent moon in a starry night sky, wearing a white hat and diaper, surrounded by glowing stars, dreamy and gentle atmosphere, digital illustration, perfect for baby lullaby or sleep music background"],"seed":520411},"clientContext":{"sessionId":";1747049348422","tool":"IMAGE_FX"},"modelInput":{"modelNameType":"IMAGEN_3_1"},"aspectRatio":"IMAGE_ASPECT_RATIO_LANDSCAPE"}
      $url = 'https://aisandbox-pa.googleapis.com/v1:runImageFx';
      $data = [
          "userInput" => [
              "candidatesCount" => 4,
              "prompts" => [
                  "A peaceful baby sleeping on a soft, crescent moon in a starry night sky, wearing a white hat and diaper, surrounded by glowing stars, dreamy and gentle atmosphere, digital illustration, perfect for baby lullaby or sleep music background"
              ],
              "seed" => 520411
          ],
          "clientContext" => [
              "sessionId" => ";1747049348422",
              "tool" => "IMAGE_FX"
          ],
          "modelInput" => [
              "modelNameType" => "IMAGEN_3_1"
          ],
          "aspectRatio" => "IMAGE_ASPECT_RATIO_LANDSCAPE"
      ];
  
      $response = Http::withHeaders([
        "Authorization" => $token,
         ])->timeout(0) // No timeout
         ->post($url, $data);

   
   
      $image = $response->json()['imagePanels'][0]['generatedImages'][0]["encodedImage"];

      $imageData = base64_decode($image);
      $randomString = \Illuminate\Support\Str::random(10);

      // Define the file path to save the image
      $filePath = storage_path('app/public/generated_image_' . $randomString . '.png');
  
      // Save the image to the file
      file_put_contents($filePath, $imageData);

      return "done";
  
  
    

    }

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


    public function overlayImages(Request $request)
{
    // Randomly select background and baby images
    $back = rand(1, 8);
    $baby = rand(1, 11);

    // Define paths
    $baseImagePath = storage_path("app/background1/$back.png");
    $overlayImagePath = storage_path("app/baby1/$baby.png");
    $cornerImagePath = storage_path("app/logo/file.png");

    // Create image resources
    $baseImageOriginal = imagecreatefromstring(file_get_contents($baseImagePath));
    $overlayImageOriginal = imagecreatefromstring(file_get_contents($overlayImagePath));
    $cornerImage = imagecreatefromstring(file_get_contents($cornerImagePath));

    // Get dimensions
    $baseWidth = imagesx($baseImageOriginal);
    $baseHeight = imagesy($baseImageOriginal);
    $overlayWidth = imagesx($overlayImageOriginal);
    $overlayHeight = imagesy($overlayImageOriginal);

    // Resize overlay image to 85% of its original size
    $newOverlayWidth = (int)($overlayWidth * 0.70);
    $newOverlayHeight = (int)($overlayHeight * 0.70);
    $overlayImage = imagecreatetruecolor($newOverlayWidth, $newOverlayHeight);
    imagealphablending($overlayImage, false);
    imagesavealpha($overlayImage, true);
    imagecopyresampled(
        $overlayImage,
        $overlayImageOriginal,
        0,
        0,
        0,
        0,
        $newOverlayWidth,
        $newOverlayHeight,
        $overlayWidth,
        $overlayHeight
    );

    // Create a true color image for the base (same size as original background)
    $baseImage = imagecreatetruecolor($baseWidth, $baseHeight);
    imagecopy($baseImage, $baseImageOriginal, 0, 0, 0, 0, $baseWidth, $baseHeight);

    // Overlay the resized baby image at the center of the background
    $posX = ($baseWidth - $newOverlayWidth) / 2;
    $posY = ($baseHeight - $newOverlayHeight) / 2;
    imagecopy($baseImage, $overlayImage, $posX, $posY, 0, 0, $newOverlayWidth, $newOverlayHeight);

    // Resize the corner logo (12% of base width)
    $cornerWidth = $baseWidth * 0.12;
    $cornerHeight = $cornerWidth * (imagesy($cornerImage) / imagesx($cornerImage));
    $cornerResized = imagecreatetruecolor($cornerWidth, $cornerHeight);
    imagealphablending($cornerResized, false);
    imagesavealpha($cornerResized, true);
    imagecopyresampled(
        $cornerResized,
        $cornerImage,
        0,
        0,
        0,
        0,
        $cornerWidth,
        $cornerHeight,
        imagesx($cornerImage),
        imagesy($cornerImage)
    );

    // Position the logo in the top-right corner with 2% margin
    $marginRight = $baseWidth * 0.02;
    $marginTop = $baseHeight * 0.02;
    $positionX = $baseWidth - $cornerWidth - $marginRight;
    $positionY = $marginTop;

    // Merge the corner logo onto the image
    imagecopy($baseImage, $cornerResized, $positionX, $positionY, 0, 0, $cornerWidth, $cornerHeight);

    // Save final image
    $outputPath = storage_path('app/public/test/merged_image_keep_overlay_size.png');
    imagepng($baseImage, $outputPath);

    // Clean up
    imagedestroy($baseImageOriginal);
    imagedestroy($baseImage);
    imagedestroy($overlayImageOriginal);
    imagedestroy($overlayImage);
    imagedestroy($cornerImage);
    imagedestroy($cornerResized);

    return 'done kemo (overlay 85%)';
}

  
   
       

}
