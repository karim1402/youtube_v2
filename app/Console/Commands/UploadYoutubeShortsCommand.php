<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UploadYoutubeShortsJob;

class UploadYoutubeShortsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upload-youtube-shorts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create and upload a vertical YouTube Short (60-180s)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching UploadYoutubeShortsJob...');
        
        UploadYoutubeShortsJob::dispatch();
        
        $this->info('Job dispatched successfully.');
        
        return Command::SUCCESS;
    }
}
