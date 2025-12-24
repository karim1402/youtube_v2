<?php

namespace App\Http\Controllers;

use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoProcessController extends Controller
{
    // 1. دمج سريع بدون إعادة ترميز
    public function concatCopy()
    {
        $listFile = storage_path('app/concat_list.txt');
        $videos = [
            storage_path('app/clips/clip1.mp4'),
            storage_path('app/clips/clip2.mp4'),
        ];

        $listContent = '';
        foreach ($videos as $video) {
            $listContent .= "file '" . $video . "'\n";
        }
        file_put_contents($listFile, $listContent);

        $output = storage_path('app/output_concat_copy.mp4');

        $cmd = "ffmpeg -f concat -safe 0 -i {$listFile} -c copy {$output} -y 2>&1";
        return shell_exec($cmd);
    }

    // 2. دمج سريع مع إعادة ترميز الصوت
    public function concatWithAudioReencode()
    {
        $listFile = storage_path('app/concat_list.txt');
        $videos = [
            storage_path('app/clips/clip1.mp4'),
            storage_path('app/clips/clip2.mp4'),
        ];

        $listContent = '';
        foreach ($videos as $video) {
            $listContent .= "file '" . $video . "'\n";
        }
        file_put_contents($listFile, $listContent);

        $output = storage_path('app/output_audio_fix.mp4');

        $cmd = "ffmpeg -f concat -safe 0 -i {$listFile} -c:v copy -c:a aac -b:a 128k {$output} -y 2>&1";
        return shell_exec($cmd);
    }

    // 3. مزج صوتين
    public function mixAudios()
    {
        $audio1 = storage_path('app/audio/1.mp3');
        $audio2 = storage_path('app/audio/2.mp3');
        $mixed = storage_path('app/output_mixed.mp3');

        if (!file_exists($audio1) || !file_exists($audio2)) {
            return "One or both audio files do not exist.";
        }

        // Simple amix without volume adjustment
        $cmd = "ffmpeg -i " . escapeshellarg($audio1)
            . " -i " . escapeshellarg($audio2)
            . " -filter_complex \"amix=inputs=2:duration=longest:dropout_transition=2\" "
            . "-vn -c:a aac -b:a 128k -y " . escapeshellarg($mixed) . " 2>&1";

        $output = shell_exec($cmd);

        return $output;
    }

    // 4. تعديل بصري بسيط
    public function applyVisualFilter()
    {
        $input = storage_path('app/clips/clip1.mp4');
        $output = storage_path('app/output_visual.mp4');

        $cmd = "ffmpeg -i {$input} -vf \"eq=contrast=1.05:saturation=1.06,scale=iw*1.02:ih*1.02,crop=iw:ih:(in_w-out_w)/2:(in_h-out_h)/2\" -c:v libx264 -preset ultrafast -c:a copy {$output} -y 2>&1";
        return shell_exec($cmd);
    }

    // 5. إضافة Sound Bar
    public function overlayWaveform()
    {
        $input = storage_path('app/clips/clip1.mp4');
        $output = storage_path('app/output_wave.mp4');

        $cmd = "ffmpeg -i {$input} -filter_complex \"[0:a]showwaves=s=1280x140:mode=line:colors=white[w];[0:v][w]overlay=0:ih-140\" -c:v libx264 -preset ultrafast -c:a copy {$output} -y 2>&1";
        return shell_exec($cmd);
    }

    public function mergeWithGreenScreen()
    {
        $python_file = storage_path('app/final_compress/edit.py');
        $process = new Process(['python', $python_file]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $output = $process->getOutput();
        dd($output);
    }

    public function mergeWithGreenScreen1($background, $greenScreen, $output)
    {

        // $greenScreen =   storage_path('app/final_compress/green_screen.mp4');
        // $background =   storage_path('app/final_compress/background.mp4');
        // $output =   storage_path('app/final_compress/output.mp4');

        if (!file_exists($greenScreen)) {
            FacadesLog::error("الفيديو غير موجود: $greenScreen");
        }
        if (!file_exists($background)) {
            FacadesLog::error("الفيديو غير موجود: $background");
        }

        $filter = "[0:v]chromakey=0x00FF00:0.2:0.1[ckout];[1:v][ckout]overlay[out]";
        $cmd = "ffmpeg -i " . escapeshellarg($greenScreen)
            . " -i " . escapeshellarg($background)
            . " -filter_complex " . escapeshellarg($filter)
            . " -map [out] -c:v libx264 -crf 18 -preset slow -y " . escapeshellarg($output) . " 2>&1";

        $outputLog = shell_exec($cmd);

        return $outputLog;
    }

    public function full_video()
    {

        //merge the effect with background

        $base_background = storage_path('app/backgrounds/1.mp4');
        $background_effect_green_screen = storage_path('app/effects/1.mp4');
        $effect_Output = storage_path('app/outputs/effect_back.mp4');

        $this->mergeWithGreenScreen1($base_background, $background_effect_green_screen, $effect_Output);


        //merge the soundbar with video

        $soundbar = storage_path('app/soundbars/1.mp4');
        $soundbar_output = storage_path('app/outputs/soundbar_output.mp4');

        $this->mergeWithGreenScreen1($effect_Output, $soundbar, $soundbar_output);

        // delete this file  $effect_Output = storage_path('app/outputs/effect_back.mp4');
        if (file_exists($effect_Output)) {
            unlink($effect_Output);
        }

        $baby_green_screen = storage_path('app/baby_greenscreen/1.mp4');
        $baby_video_output = storage_path('app/outputs/baby_output.mp4');
        $this->mergeWithGreenScreen1($soundbar_output, $baby_green_screen, $baby_video_output);

        // delete this file  $final_output = storage_path('app/outputs/soundbar_output.mp4');
        if (file_exists($soundbar_output)) {
            unlink($soundbar_output);
        }

        $sleep_effect_green_screen = storage_path('app/sleep_effects/1.mp4');
        $final_video_output = storage_path('app/outputs/final_video.mp4');
        $this->mergeWithGreenScreen1($baby_video_output, $sleep_effect_green_screen, $final_video_output);

        // delete this file  $final_output = storage_path('app/outputs/baby_output.mp4');
        if (file_exists($baby_video_output)) {
            unlink($baby_video_output);
        }

        return response()->json(['message' => 'تم إنشاء الفيديو بنجاح', 'video_path' => $final_video_output]);
    }


    public function full_video_fast()
    {
        $background = storage_path('app/backgrounds/1.mp4');
        $effect = storage_path('app/effects/1.mp4');
        $soundbar = storage_path('app/soundbars/1.mp4');
        $baby = storage_path('app/baby_greenscreen/1.mp4');
        $sleep = storage_path('app/sleep_effects/1.mp4');
        $output = storage_path('app/outputs/final_video.mp4');

        // Check files exist
        foreach ([$background, $effect, $soundbar, $baby, $sleep] as $file) {
            if (!file_exists($file)) {
                FacadesLog::error("الفيديو غير موجود: $file");
            }
        }

        // Build filter_complex
        $filter = "[1:v]chromakey=0x00FF00:0.2:0.1[eff];"
            . "[0:v][eff]overlay[bg_eff];"
            . "[2:v]chromakey=0x00FF00:0.2:0.1[sb];"
            . "[bg_eff][sb]overlay[sb_eff];"
            . "[3:v]chromakey=0x00FF00:0.2:0.1[baby];"
            . "[sb_eff][baby]overlay[baby_eff];"
            . "[4:v]chromakey=0x00FF00:0.2:0.1[sleep];"
            . "[baby_eff][sleep]overlay[out]";

        $cmd = "ffmpeg -i " . escapeshellarg($background)
            . " -i " . escapeshellarg($effect)
            . " -i " . escapeshellarg($soundbar)
            . " -i " . escapeshellarg($baby)
            . " -i " . escapeshellarg($sleep)
            . " -filter_complex " . escapeshellarg($filter)
            . " -map [out] -c:v libx264 -crf 18 -preset slow -y " . escapeshellarg($output) . " 2>&1";

        $outputLog = shell_exec($cmd);

        return response()->json(['message' => 'تم إنشاء الفيديو بنجاح', 'video_path' => $output, 'ffmpeg_log' => $outputLog]);
    }


    public function mergeTwoAudioFiles()
    {
        $audioDir = storage_path('app/audio');
        $outputDir = storage_path('app/outputs');
        $audio1 = $audioDir . '/1.mp3';
        $audio2 = $audioDir . '/2.mp3';
        $outputPath = $outputDir . '/merged_audio.mp3';

        // Ensure output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        if (!file_exists($audio1)) {
            return response()->json(['success' => false, 'error' => 'First audio file not found']);
        }
        if (!file_exists($audio2)) {
            return response()->json(['success' => false, 'error' => 'Second audio file not found']);
        }

        // FFmpeg command to mix two audio files together
        $ffmpegCmd = "ffmpeg -y -i \"$audio1\" -i \"$audio2\" -filter_complex \"[0:0][1:0]amix=inputs=2:duration=longest:dropout_transition=2\" -c:a libmp3lame \"$outputPath\" 2>&1";
        exec($ffmpegCmd, $output, $resultCode);

        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputPath,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public function mergeFinalVideoWithAudio()
    {
        $videoPath = storage_path('app/outputs/final_video.mp4');
        $audioPath = storage_path('app/outputs/merged_audio.mp3');
        $outputPath = storage_path('app/outputs/final_video_with_audio.mp4');

        if (!file_exists($videoPath)) {
            return response()->json(['success' => false, 'error' => 'Video file not found']);
        }
        if (!file_exists($audioPath)) {
            return response()->json(['success' => false, 'error' => 'Audio file not found']);
        }

        // FFmpeg command to merge audio and video
        $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v copy -c:a aac -shortest " . escapeshellarg($outputPath) . " 2>&1";

        exec($cmd, $output, $resultCode);
        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputPath,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public function repeatFinalVideo()
    {
        $inputVideo = storage_path('app/outputs/final_video_with_audio.mp4');
        $outputVideo = storage_path('app/outputs/final_video_repeated.mp4');

        if (!file_exists($inputVideo)) {
            return response()->json(['success' => false, 'error' => 'Input video not found']);
        }

        // FFmpeg command to repeat the video 20 times using stream_loop
        $cmd = "ffmpeg -y -stream_loop 19 -i " . escapeshellarg($inputVideo)
             . " -c:v libx264 -c:a aac -b:a 128k -preset ultrafast " . escapeshellarg($outputVideo) . " 2>&1";
        exec($cmd, $output, $resultCode);
        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputVideo,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public function repeatFinalVideoUltraFast()
    {
        $inputVideo = storage_path('app/outputs/final_video_with_audio.mp4');
        $outputVideo = storage_path('app/outputs/final_video_repeatedtt.mp4');

        if (!file_exists($inputVideo)) {
            return response()->json(['success' => false, 'error' => 'Input video not found']);
        }

        // Use stream_loop and avoid re-encoding for maximum speed
        $cmd = "ffmpeg -y -stream_loop 19 -i " . escapeshellarg($inputVideo)
             . " -c copy -map 0 " . escapeshellarg($outputVideo) . " 2>&1";
        exec($cmd, $output, $resultCode);
        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputVideo,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public function mergeSameVideoMultipleTimes( )
{

    $repeatCount = 12;
    $videoPath =  storage_path('app/outputs/final_video_with_audio.mp4');
    if (!file_exists($videoPath)) {
        return false;
    }
    $outputPath = storage_path('app/outputs/merged_repeated10.mp4');
    $listFile = storage_path('app/videos_repeat.txt');
    $fileListContent = '';
    for ($i = 0; $i < $repeatCount; $i++) {
        $fileListContent .= "file '" . $videoPath . "'\n";
    }
    file_put_contents($listFile, $fileListContent);

    // Use re-encoding for compatibility
    $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile)
               . " -c:v libx264 -c:a aac -b:a 128k -preset fast -y " . escapeshellarg($outputPath);

    exec($ffmpegCmd, $output, $returnVar);

    return $returnVar === 0 ? $outputPath : false;
}
  
   public function final_video(){
        // ini_set('max_execution_time', 3000); // 5 minutes
        // ini_set('memory_limit', '4G'); // 4GB

        $this->full_video_fast();
        // $this->mergeTwoAudioFiles();
        // $final_video_with_audio = $this->mergeFinalVideoWithAudio();
        // $final_repeated_video = $this->mergeSameVideoMultipleTimes();
        // dd('final video with audio: ');

    }
}
