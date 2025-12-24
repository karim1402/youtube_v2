<?php

use App\Jobs\UploadVideoJob;
use App\Jobs\UploadVideoPureJob;
use App\Jobs\UploadVideoJobtest;
use App\Jobs\StreamToYouTubeJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('upload:video', function () {
    UploadVideoJob::dispatch();
    //queue:work --queue=high,default run this 
    
    Artisan::call('queue:work', [
        '--queue' => 'high,default',
        '--stop-when-empty' => true, // Stops the worker after processing all jobs
    ]);

    $this->info('UploadVideoJob dispatched successfully.');
})->purpose('Dispatch the UploadVideoJob');

Artisan::command('upload:video0', function () {

    // UploadVideoJob::dispatchSync();
    // sleep(5400); //sleep for 1h and 30min
    UploadVideoJobtest::dispatchSync(); // Runs the job immediately, not queued

    $this->info('UploadVideoJobtest finished synchronously.');
})->purpose('Dispatch and run UploadVideoJobtest synchronously');

Artisan::command('upload:purevideo', function () {
    UploadVideoPureJob::dispatch();
    //queue:work --queue=high,default run this 
    
    Artisan::call('queue:work', [
        '--queue' => 'high,default',
        '--stop-when-empty' => true, // Stops the worker after processing all jobs
    ]);

    $this->info('UploadVideoJob dispatched successfully.');
})->purpose('Dispatch the UploadVideoJob');


Artisan::command('stream:youtube', function () {
    StreamToYouTubeJob::dispatch(); 
    //queue:work --queue=high,default run this 
    
    Artisan::call('queue:work', [
        '--queue' => 'high,default',
        '--stop-when-empty' => true, // Stops the worker after processing all jobs
    ]);

    $this->info('UploadVideoJob dispatched successfully.');
})->purpose('Dispatch the UploadVideoJob');
