<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UploadLongVideoJob;

class UploadLongVideoCommand extends Command
{
    protected $signature = 'app:upload-long-video';
    protected $description = 'Create and upload long videos (1h, 3h, 10h) to YouTube sequentially';

    public function handle()
    {
        $this->info('Dispatching UploadLongVideoJob...');
        
        UploadLongVideoJob::dispatch();
        
        $this->info('Job dispatched. Run queue:work to process.');
        
        return Command::SUCCESS;
    }
}
