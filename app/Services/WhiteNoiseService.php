<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WhiteNoiseService
{
    /**
     * Generate white noise audio file using FFmpeg
     *
     * @param int $duration Duration in seconds
     * @param string|null $filename Custom filename (optional)
     * @param float $volume Volume level (0.1 to 1.0, default 0.4 for soft sound)
     * @return array
     */
    public function generateWhiteNoise(int $duration = 600, ?string $filename = null, float $volume = 0.1): array
    {
        try {
            // Create directory if it doesn't exist
            $directory = storage_path('app/white_noise');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Generate filename with microseconds for uniqueness
            if (!$filename) {
                $filename = 'white_noise_' . time() . '_' . mt_rand(1000, 9999) . '.mp3';
            } else {
                // Ensure .mp3 extension
                if (!str_ends_with($filename, '.mp3')) {
                    $filename .= '.mp3';
                }
            }

            // Ensure volume is within valid range
            $volume = max(0.1, min(1.0, $volume));
            
            // Generate STRONG unique variations for each audio
            $seed = mt_rand(0, 999999);
            
            // Add random frequency variations for MORE difference
            $bassBoost = mt_rand(0, 5);           // Random bass: 0-5 dB
            $trebleBoost = mt_rand(-3, 3);        // Random treble: -3 to +3 dB
            $midCut = mt_rand(-2, 2);             // Random mid: -2 to +2 dB
            
            // Add random amplitude variation
            $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 to 1.05

            $filePath = $directory . '/' . $filename;

            // Check if FFmpeg is available
            if (!$this->checkFFmpeg()) {
                return [
                    'status' => 'error',
                    'message' => 'FFmpeg is not installed or not available in PATH',
                    'file_path' => null,
                ];
            }

            // Build FFmpeg command with clean EQ-only randomization
            // Creates variation without degrading audio quality
            $audioFilters = [
                "volume={$volume}*{$amplitudeVar}",                    // Random volume variation
                "equalizer=f=100:t=q:w=1:g={$bassBoost}",              // Random bass boost
                "equalizer=f=1000:t=q:w=1:g={$midCut}",                // Random mid adjustment
                "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"            // Random treble boost
            ];
            
            $filterComplex = implode(',', $audioFilters);
            
            $command = [
                'ffmpeg',
                '-y',
                '-f', 'lavfi',
                '-i', "anoisesrc=color=white:duration={$duration}:sample_rate=44100:seed={$seed}",
                '-af', $filterComplex,
                '-c:a', 'libmp3lame',
                '-q:a', '2',
                '-ar', '44100',
                $filePath
            ];

            // Execute FFmpeg command
            $process = new Process($command);
            $process->setTimeout($duration + 60); // Add buffer time
            $process->run();

            // Check if process was successful
            if (!$process->isSuccessful()) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate white noise: ' . $process->getErrorOutput(),
                    'file_path' => null,
                ];
            }

            // Verify file was created
            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'White noise file was not created',
                    'file_path' => null,
                ];
            }

            $fileSize = filesize($filePath);
            $relativePath = 'white_noise/' . $filename;

            return [
                'status' => 'success',
                'message' => 'White noise generated successfully with unique audio signature',
                'file_path' => $filePath,
                'relative_path' => $relativePath,
                'filename' => $filename,
                'duration' => $duration,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'volume' => $volume,
                'seed' => $seed,
                'audio_signature' => [
                    'bass_boost' => $bassBoost . ' dB',
                    'treble_boost' => $trebleBoost . ' dB',
                    'mid_adjustment' => $midCut . ' dB',
                    'amplitude_variation' => round($amplitudeVar, 3),
                ],
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'file_path' => null,
            ];
        }
    }

    /**
     * Generate pink noise audio file using FFmpeg
     *
     * @param int $duration Duration in seconds
     * @param string|null $filename Custom filename (optional)
     * @param float $volume Volume level (0.1 to 1.0, default 0.4 for soft sound)
     * @return array
     */
    public function generatePinkNoise(int $duration = 600, ?string $filename = null, float $volume = 0.4): array
    {
        try {
            $directory = storage_path('app/white_noise');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            if (!$filename) {
                $filename = 'pink_noise_' . time() . '_' . mt_rand(1000, 9999) . '.mp3';
            } else {
                if (!str_ends_with($filename, '.mp3')) {
                    $filename .= '.mp3';
                }
            }

            // Ensure volume is within valid range
            $volume = max(0.1, min(1.0, $volume));
            
            // Generate STRONG unique variations for each audio
            $seed = mt_rand(0, 999999);
            
            // Add random frequency variations for MORE difference
            $bassBoost = mt_rand(0, 5);           // Random bass: 0-5 dB
            $trebleBoost = mt_rand(-3, 3);        // Random treble: -3 to +3 dB
            $midCut = mt_rand(-2, 2);             // Random mid: -2 to +2 dB
            
            // Add random amplitude variation
            $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 to 1.05

            $filePath = $directory . '/' . $filename;

            if (!$this->checkFFmpeg()) {
                return [
                    'status' => 'error',
                    'message' => 'FFmpeg is not installed or not available in PATH',
                    'file_path' => null,
                ];
            }

            // Build FFmpeg command with clean EQ-only randomization
            $audioFilters = [
                "volume={$volume}*{$amplitudeVar}",
                "equalizer=f=100:t=q:w=1:g={$bassBoost}",
                "equalizer=f=1000:t=q:w=1:g={$midCut}",
                "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"
            ];
            
            $filterComplex = implode(',', $audioFilters);
            
            $command = [
                'ffmpeg',
                '-y',
                '-f', 'lavfi',
                '-i', "anoisesrc=color=pink:duration={$duration}:sample_rate=44100:seed={$seed}",
                '-af', $filterComplex,
                '-c:a', 'libmp3lame',
                '-q:a', '2',
                '-ar', '44100',
                $filePath
            ];

            $process = new Process($command);
            $process->setTimeout($duration + 60);
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate pink noise: ' . $process->getErrorOutput(),
                    'file_path' => null,
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'Pink noise file was not created',
                    'file_path' => null,
                ];
            }

            $fileSize = filesize($filePath);
            $relativePath = 'white_noise/' . $filename;

            return [
                'status' => 'success',
                'message' => 'Pink noise generated successfully with unique audio signature',
                'file_path' => $filePath,
                'relative_path' => $relativePath,
                'filename' => $filename,
                'duration' => $duration,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'volume' => $volume,
                'seed' => $seed,
                'audio_signature' => [
                    'bass_boost' => $bassBoost . ' dB',
                    'treble_boost' => $trebleBoost . ' dB',
                    'mid_adjustment' => $midCut . ' dB',
                    'amplitude_variation' => round($amplitudeVar, 3),
                ],
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'file_path' => null,
            ];
        }
    }

    /**
     * Generate brown noise audio file using FFmpeg
     *
     * @param int $duration Duration in seconds
     * @param string|null $filename Custom filename (optional)
     * @param float $volume Volume level (0.1 to 1.0, default 0.4 for soft sound)
     * @return array
     */
    public function generateBrownNoise(int $duration = 600, ?string $filename = null, float $volume = 0.4): array
    {
        try {
            $directory = storage_path('app/white_noise');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            if (!$filename) {
                $filename = 'brown_noise_' . time() . '_' . mt_rand(1000, 9999) . '.mp3';
            } else {
                if (!str_ends_with($filename, '.mp3')) {
                    $filename .= '.mp3';
                }
            }

            // Ensure volume is within valid range
            $volume = max(0.1, min(1.0, $volume));
            
            // Generate STRONG unique variations for each audio
            $seed = mt_rand(0, 999999);
            
            // Add random frequency variations for MORE difference
            $bassBoost = mt_rand(0, 5);           // Random bass: 0-5 dB
            $trebleBoost = mt_rand(-3, 3);        // Random treble: -3 to +3 dB
            $midCut = mt_rand(-2, 2);             // Random mid: -2 to +2 dB
            
            // Add random amplitude variation
            $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 to 1.05

            $filePath = $directory . '/' . $filename;

            if (!$this->checkFFmpeg()) {
                return [
                    'status' => 'error',
                    'message' => 'FFmpeg is not installed or not available in PATH',
                    'file_path' => null,
                ];
            }

            // Build FFmpeg command with clean EQ-only randomization
            $audioFilters = [
                "volume={$volume}*{$amplitudeVar}",
                "equalizer=f=100:t=q:w=1:g={$bassBoost}",
                "equalizer=f=1000:t=q:w=1:g={$midCut}",
                "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"
            ];
            
            $filterComplex = implode(',', $audioFilters);
            
            $command = [
                'ffmpeg',
                '-y',
                '-f', 'lavfi',
                '-i', "anoisesrc=color=brown:duration={$duration}:sample_rate=44100:seed={$seed}",
                '-af', $filterComplex,
                '-c:a', 'libmp3lame',
                '-q:a', '2',
                '-ar', '44100',
                $filePath
            ];

            $process = new Process($command);
            $process->setTimeout($duration + 60);
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate brown noise: ' . $process->getErrorOutput(),
                    'file_path' => null,
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'Brown noise file was not created',
                    'file_path' => null,
                ];
            }

            $fileSize = filesize($filePath);
            $relativePath = 'white_noise/' . $filename;

            return [
                'status' => 'success',
                'message' => 'Brown noise generated successfully with unique audio signature',
                'file_path' => $filePath,
                'relative_path' => $relativePath,
                'filename' => $filename,
                'duration' => $duration,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'volume' => $volume,
                'seed' => $seed,
                'audio_signature' => [
                    'bass_boost' => $bassBoost . ' dB',
                    'treble_boost' => $trebleBoost . ' dB',
                    'mid_adjustment' => $midCut . ' dB',
                    'amplitude_variation' => round($amplitudeVar, 3),
                ],
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'file_path' => null,
            ];
        }
    }

    /**
     * List all generated noise files
     *
     * @return array
     */
    public function listNoiseFiles(): array
    {
        try {
            $directory = storage_path('app/white_noise');
            
            if (!file_exists($directory)) {
                return [
                    'status' => 'success',
                    'message' => 'No files found',
                    'files' => [],
                    'total_count' => 0,
                ];
            }

            $files = array_diff(scandir($directory), ['.', '..']);
            $fileList = [];

            foreach ($files as $file) {
                $filePath = $directory . '/' . $file;
                if (is_file($filePath)) {
                    $fileList[] = [
                        'filename' => $file,
                        'path' => $filePath,
                        'relative_path' => 'white_noise/' . $file,
                        'size' => filesize($filePath),
                        'size_mb' => round(filesize($filePath) / 1024 / 1024, 2),
                        'created_at' => date('Y-m-d H:i:s', filectime($filePath)),
                        'modified_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                    ];
                }
            }

            return [
                'status' => 'success',
                'message' => 'Files retrieved successfully',
                'files' => $fileList,
                'total_count' => count($fileList),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'files' => [],
            ];
        }
    }

    /**
     * Delete a noise file
     *
     * @param string $filename
     * @return array
     */
    public function deleteNoiseFile(string $filename): array
    {
        try {
            $filePath = storage_path('app/white_noise/' . $filename);

            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'File not found',
                ];
            }

            if (unlink($filePath)) {
                return [
                    'status' => 'success',
                    'message' => 'File deleted successfully',
                    'filename' => $filename,
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to delete file',
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Exception occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if FFmpeg is available
     *
     * @return bool
     */
    protected function checkFFmpeg(): bool
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    

    /**
     * Get available noise colors
     *
     * @return array
     */
    public function getAvailableNoiseColors(): array
    {
        return [
            'white' => 'White noise - Equal intensity across all frequencies',
            'pink' => 'Pink noise - Softer, more balanced sound',
            'brown' => 'Brown noise - Deeper, rumbling sound',
        ];
    }
}
