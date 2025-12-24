<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ThumbnailService
{
    protected int $backgroundMax = 35;
    protected int $babyMax = 33;
    protected float $babyScale = 0.70; // 70% of original size
    protected float $logoScale = 0.12; // 12% of background width
    protected float $margin = 0.02; // 2% margin

    /**
     * Create thumbnail by overlaying baby image on background with logo
     * 
     * Performance improvements:
     * - Reuse image resources
     * - Optimize image operations
     * - Proper resource cleanup
     */
    public function createThumbnail(string $outputPath = null): string
    {
        $outputPath = $outputPath ?? storage_path('app/public/merged_image.png');

        try {
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0775, true);
            }

            // Select random assets
            $backgroundPath = $this->getRandomAsset('background', $this->backgroundMax);
            $babyPath = $this->getRandomAsset('baby', $this->babyMax);
            $logoPath = storage_path('app/logo/file.png');

            // Validate files exist
            $this->validateFiles([$backgroundPath, $babyPath, $logoPath]);

            // Load images
            $backgroundImg = imagecreatefromstring(file_get_contents($backgroundPath));
            $babyImg = imagecreatefromstring(file_get_contents($babyPath));
            $logoImg = imagecreatefromstring(file_get_contents($logoPath));

            // Get dimensions
            $bgWidth = imagesx($backgroundImg);
            $bgHeight = imagesy($backgroundImg);

            // Create final image
            $finalImage = imagecreatetruecolor($bgWidth, $bgHeight);
            imagecopy($finalImage, $backgroundImg, 0, 0, 0, 0, $bgWidth, $bgHeight);

            // Overlay baby (resized and centered)
            $this->overlayBaby($finalImage, $babyImg, $bgWidth, $bgHeight);

            // Overlay logo (top-right corner)
            $this->overlayLogo($finalImage, $logoImg, $bgWidth, $bgHeight);

            // Save final image
            imagepng($finalImage, $outputPath, 9); // Max compression

            // Cleanup
            imagedestroy($backgroundImg);
            imagedestroy($babyImg);
            imagedestroy($logoImg);
            imagedestroy($finalImage);

            Log::info('Thumbnail created successfully', ['output' => $outputPath]);

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('Thumbnail creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Overlay baby image (resized and centered)
     */
    protected function overlayBaby($finalImage, $babyImg, int $bgWidth, int $bgHeight): void
    {
        $babyWidth = imagesx($babyImg);
        $babyHeight = imagesy($babyImg);

        // Calculate new dimensions
        $newBabyWidth = (int)($babyWidth * $this->babyScale);
        $newBabyHeight = (int)($babyHeight * $this->babyScale);

        // Resize baby image
        $resizedBaby = imagecreatetruecolor($newBabyWidth, $newBabyHeight);
        imagealphablending($resizedBaby, false);
        imagesavealpha($resizedBaby, true);
        
        imagecopyresampled(
            $resizedBaby, $babyImg,
            0, 0, 0, 0,
            $newBabyWidth, $newBabyHeight,
            $babyWidth, $babyHeight
        );

        // Center on background
        $posX = (int)(($bgWidth - $newBabyWidth) / 2);
        $posY = (int)(($bgHeight - $newBabyHeight) / 2);

        imagecopy($finalImage, $resizedBaby, $posX, $posY, 0, 0, $newBabyWidth, $newBabyHeight);
        imagedestroy($resizedBaby);
    }

    /**
     * Overlay logo (top-right corner with margin)
     */
    protected function overlayLogo($finalImage, $logoImg, int $bgWidth, int $bgHeight): void
    {
        $logoWidth = imagesx($logoImg);
        $logoHeight = imagesy($logoImg);

        // Calculate new dimensions (proportional)
        $newLogoWidth = (int)($bgWidth * $this->logoScale);
        $newLogoHeight = (int)($newLogoWidth * ($logoHeight / $logoWidth));

        // Resize logo
        $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
        imagealphablending($resizedLogo, false);
        imagesavealpha($resizedLogo, true);
        
        imagecopyresampled(
            $resizedLogo, $logoImg,
            0, 0, 0, 0,
            $newLogoWidth, $newLogoHeight,
            $logoWidth, $logoHeight
        );

        // Position top-right with margin
        $posX = (int)($bgWidth - $newLogoWidth - ($bgWidth * $this->margin));
        $posY = (int)($bgHeight * $this->margin);

        imagecopy($finalImage, $resizedLogo, $posX, $posY, 0, 0, $newLogoWidth, $newLogoHeight);
        imagedestroy($resizedLogo);
    }

    /**
     * Get random asset path
     */
    protected function getRandomAsset(string $type, int $max): string
    {
        $index = rand(1, $max);
        return storage_path("app/{$type}/{$index}.png");
    }

    /**
     * Validate that all files exist
     */
    protected function validateFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                throw new \RuntimeException("File not found: {$path}");
            }
        }
    }

    /**
     * Configure thumbnail settings
     */
    public function configure(array $settings): self
    {
        if (isset($settings['baby_scale'])) {
            $this->babyScale = $settings['baby_scale'];
        }
        if (isset($settings['logo_scale'])) {
            $this->logoScale = $settings['logo_scale'];
        }
        if (isset($settings['margin'])) {
            $this->margin = $settings['margin'];
        }

        return $this;
    }
}
