<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UploadShortVideoJob;
use Illuminate\Support\Facades\Artisan;

class UploadShortVideoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upload-short-video';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create and upload a short video (5-30 minutes) to YouTube and process queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching UploadShortVideoJob...');
        
        UploadShortVideoJob::dispatch();
        
        $this->info('Job dispatched. Starting queue worker...');
        
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--timeout' => 3600,
        ]);
        
        $this->info('Queue processing completed.');
        
        return Command::SUCCESS;
    }
}
