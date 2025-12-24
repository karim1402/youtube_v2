<?php


namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StreamToYouTubeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $inputVideo;
    protected $rtmpUrl;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->inputVideo = '/home/karim/htdocs/socialproductionmedia.com/storage/app/outputs/finaloutpt123.mp4';
        $this->rtmpUrl = 'rtmps://x.rtmp.youtube.com/live2/apbf-1j58-x0wv-bv6y-bmuz';
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $cmd = "ffmpeg -re -stream_loop -1 -i " . escapeshellarg($this->inputVideo)
             . " -c:v libx264 -preset veryfast -b:v 1500k -maxrate 1500k -bufsize 3000k -pix_fmt yuv420p -g 50"
             . " -c:a aac -b:a 96k -ar 44100"
             . " -f flv " . escapeshellarg($this->rtmpUrl) . " 2>&1";

        Log::info('Starting YouTube stream: ' . $cmd);

        exec($cmd, $output, $resultCode);

        Log::info('YouTube stream finished', [
            'resultCode' => $resultCode,
            'output' => implode("\n", $output)
        ]);
    }
}