<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class uplodepurecommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:uplode-pure-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     // Dispatch the UploadVideoJob
    //     \App\Jobs\UploadVideoJob::dispatch();
    //     // Run the queue worker
    //     \Artisan::call('queue:work', [
    //         '--queue' => 'high,default',
    //         '--stop-when-empty' => true, // Stops the worker after processing all jobs
    //     ]);
    //     $this->info('UploadVideoJob dispatched successfully.');
    // }
}
