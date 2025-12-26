<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UploadLongVideoJob;
use Illuminate\Support\Facades\Artisan;

class UploadLongVideoCommand extends Command
{
    protected $signature = 'app:upload-long-video';
    protected $description = 'Create and upload long videos (1h, 3h, 10h) to YouTube and process queue';

    public function handle()
    {
        $this->info('Dispatching UploadLongVideoJob...');
        
        UploadLongVideoJob::dispatch();
        
        $this->info('Job dispatched. Starting queue worker...');
        
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--timeout' => 3600,
        ]);
        
        $this->info('Queue processing completed.');
        
        return Command::SUCCESS;
    }
}
