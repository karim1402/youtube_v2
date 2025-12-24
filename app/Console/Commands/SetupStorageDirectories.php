<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupStorageDirectories extends Command
{
    protected $signature = 'setup:storage';
    protected $description = 'Create all required storage directories for video processing';

    public function handle()
    {
        $this->info('🗂️  Setting up storage directories...');
        $this->newLine();

        $directories = [
            // Input directories (for media assets)
            'storage/app/backgrounds' => 'Background videos (1.mp4 - 11.mp4)',
            'storage/app/effects' => 'Effect overlays (1.mp4 - 8.mp4)',
            'storage/app/soundbars' => 'Audio visualizers (1.mp4 - 8.mp4)',
            'storage/app/baby_greenscreen' => 'Baby animations (1.mp4 - 6.mp4)',
            'storage/app/sleep_effects' => 'Sleep effects (1.mp4)',
            'storage/app/audio' => 'Audio tracks (1.mp3 - 6.mp3)',
            'storage/app/logo' => 'Channel logo (file.png)',
            'storage/app/background' => 'Thumbnail backgrounds (1.png - 35.png)',
            'storage/app/baby' => 'Thumbnail baby images (1.png - 33.png)',
            
            // Processing directories
            'storage/app/finals' => 'Intermediate processing files',
            'storage/app/copys' => 'Temporary video copies (legacy)',
            
            // Output directories
            'storage/app/outputs' => 'Final video outputs',
            'storage/app/public' => 'Public files (thumbnails)',
            'storage/app/white_noise' => 'Generated white noise audio',
        ];

        $created = 0;
        $existing = 0;

        foreach ($directories as $path => $description) {
            $fullPath = base_path($path);
            
            if (File::exists($fullPath)) {
                $this->line("  ✓ <fg=gray>{$path}</> - {$description}");
                $existing++;
            } else {
                File::makeDirectory($fullPath, 0775, true, true);
                $this->line("  <fg=green>✓ Created:</> {$path} - {$description}");
                $created++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary:");
        $this->line("  - Created: {$created} directories");
        $this->line("  - Existing: {$existing} directories");
        $this->line("  - Total: " . ($created + $existing) . " directories");

        $this->newLine();
        $this->info('✅ Storage directories setup complete!');
        $this->newLine();

        // Show next steps
        $this->comment('📋 Next steps:');
        $this->line('  1. Add your media assets to the directories above');
        $this->line('  2. Run a test: php artisan test:optimized-pipeline --step=video --copies=10');
        $this->line('  3. Check output: open storage/app/outputs/finaloutpt123.mp4');

        return Command::SUCCESS;
    }
}
