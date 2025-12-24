<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UploadShortVideoJob;

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
    protected $description = 'Create and upload a short video (5-30 minutes) to YouTube';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching UploadShortVideoJob...');
        
        UploadShortVideoJob::dispatch();
        
        $this->info('Job dispatched successfully. Run queue:work to process.');
        
        return Command::SUCCESS;
    }
}
