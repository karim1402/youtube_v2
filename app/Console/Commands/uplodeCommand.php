<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class uplodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:uplode-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Dispatch the OPTIMIZED UploadVideoJob
        \App\Jobs\UploadVideoJobOptimized::dispatch(
            channelId: '2',  // Your channel ID
            videoLengthHours: 10,  // 10-hour videos
            privacy: 'public'  // or 'private', 'unlisted'
        );
        
        // Run the queue worker
        \Artisan::call('queue:work', [
            '--queue' => 'high,default',
            '--stop-when-empty' => true, // Stops the worker after processing all jobs
        ]);
        
        $this->info('UploadVideoJobOptimized dispatched successfully with all features!');
    }
}
