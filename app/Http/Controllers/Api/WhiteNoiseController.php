<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhiteNoiseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WhiteNoiseController extends Controller
{
    protected $whiteNoiseService;

    public function __construct(WhiteNoiseService $whiteNoiseService)
    {
        $this->whiteNoiseService = $whiteNoiseService;
    }

    /**
     * Generate white noise audio file
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateWhiteNoise(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'duration' => 'nullable|integer|min:1|max:36000', // Max 10 hours
                'filename' => 'nullable|string|max:255',
                'volume' => 'nullable|numeric|min:0.1|max:1.0', // Volume level
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $duration = $request->input('duration', 600); // Default 10 minutes
            $filename = $request->input('filename', null);
            $volume = $request->input('volume', 0.4); // Default soft volume

            $result = $this->whiteNoiseService->generateWhiteNoise($duration, $filename, $volume);

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => [
                        'file_path' => $result['file_path'],
                        'relative_path' => $result['relative_path'],
                        'filename' => $result['filename'],
                        'duration' => $result['duration'],
                        'duration_formatted' => gmdate('H:i:s', $result['duration']),
                        'file_size' => $result['file_size'],
                        'file_size_mb' => $result['file_size_mb'],
                        'volume' => $result['volume'],
                        'seed' => $result['seed'],
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate pink noise audio file
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generatePinkNoise(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'duration' => 'nullable|integer|min:1|max:36000',
                'filename' => 'nullable|string|max:255',
                'volume' => 'nullable|numeric|min:0.1|max:1.0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $duration = $request->input('duration', 600);
            $filename = $request->input('filename', null);
            $volume = $request->input('volume', 0.4);

            $result = $this->whiteNoiseService->generatePinkNoise($duration, $filename, $volume);

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => [
                        'file_path' => $result['file_path'],
                        'relative_path' => $result['relative_path'],
                        'filename' => $result['filename'],
                        'duration' => $result['duration'],
                        'duration_formatted' => gmdate('H:i:s', $result['duration']),
                        'file_size' => $result['file_size'],
                        'file_size_mb' => $result['file_size_mb'],
                        'volume' => $result['volume'],
                        'seed' => $result['seed'],
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate brown noise audio file
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateBrownNoise(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'duration' => 'nullable|integer|min:1|max:36000',
                'filename' => 'nullable|string|max:255',
                'volume' => 'nullable|numeric|min:0.1|max:1.0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $duration = $request->input('duration', 600);
            $filename = $request->input('filename', null);
            $volume = $request->input('volume', 0.4);

            $result = $this->whiteNoiseService->generateBrownNoise($duration, $filename, $volume);

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => [
                        'file_path' => $result['file_path'],
                        'relative_path' => $result['relative_path'],
                        'filename' => $result['filename'],
                        'duration' => $result['duration'],
                        'duration_formatted' => gmdate('H:i:s', $result['duration']),
                        'file_size' => $result['file_size'],
                        'file_size_mb' => $result['file_size_mb'],
                        'volume' => $result['volume'],
                        'seed' => $result['seed'],
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate noise by type (white/pink/brown)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateNoise(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:white,pink,brown',
                'duration' => 'nullable|integer|min:1|max:36000',
                'filename' => 'nullable|string|max:255',
                'volume' => 'nullable|numeric|min:0.1|max:1.0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $type = $request->input('type');
            $duration = $request->input('duration', 600);
            $filename = $request->input('filename', null);
            $volume = $request->input('volume', 0.4);

            $result = match($type) {
                'white' => $this->whiteNoiseService->generateWhiteNoise($duration, $filename, $volume),
                'pink' => $this->whiteNoiseService->generatePinkNoise($duration, $filename, $volume),
                'brown' => $this->whiteNoiseService->generateBrownNoise($duration, $filename, $volume),
            };

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => [
                        'type' => $type,
                        'file_path' => $result['file_path'],
                        'relative_path' => $result['relative_path'],
                        'filename' => $result['filename'],
                        'duration' => $result['duration'],
                        'duration_formatted' => gmdate('H:i:s', $result['duration']),
                        'file_size' => $result['file_size'],
                        'file_size_mb' => $result['file_size_mb'],
                        'volume' => $result['volume'],
                        'seed' => $result['seed'],
                    ],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all generated noise files
     *
     * @return JsonResponse
     */
    public function listFiles(): JsonResponse
    {
        try {
            $result = $this->whiteNoiseService->listNoiseFiles();

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
                'data' => $result['files'],
                'total_count' => $result['total_count'] ?? 0,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a noise file
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteFile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $filename = $request->input('filename');
            $result = $this->whiteNoiseService->deleteNoiseFile($filename);

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'filename' => $result['filename'],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available noise types
     *
     * @return JsonResponse
     */
    public function getNoiseTypes(): JsonResponse
    {
        try {
            $types = $this->whiteNoiseService->getAvailableNoiseColors();

            return response()->json([
                'status' => 'success',
                'message' => 'Available noise types retrieved',
                'data' => $types,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check for white noise service
     *
     * @return JsonResponse
     */
    public function healthCheck(): JsonResponse
    {
        try {
            // Check FFmpeg availability
            $ffmpegAvailable = false;
            try {
                $process = new \Symfony\Component\Process\Process(['ffmpeg', '-version']);
                $process->run();
                $ffmpegAvailable = $process->isSuccessful();
            } catch (\Exception $e) {
                $ffmpegAvailable = false;
            }

            // Check storage directory
            $directory = storage_path('app/white_noise');
            $directoryExists = file_exists($directory);
            $directoryWritable = $directoryExists ? is_writable($directory) : false;

            $allChecksPass = $ffmpegAvailable && $directoryExists && $directoryWritable;

            return response()->json([
                'status' => $allChecksPass ? 'success' : 'warning',
                'message' => $allChecksPass ? 'White noise service is ready' : 'Some components need attention',
                'checks' => [
                    'ffmpeg_available' => $ffmpegAvailable,
                    'directory_exists' => $directoryExists,
                    'directory_writable' => $directoryWritable,
                    'directory_path' => $directory,
                    'php_version' => PHP_VERSION,
                ],
                'recommendations' => $allChecksPass ? [] : array_filter([
                    !$ffmpegAvailable ? 'Install FFmpeg and add it to PATH' : null,
                    !$directoryExists ? 'Create directory: storage/app/white_noise' : null,
                    !$directoryWritable ? 'Make directory writable' : null,
                ]),
            ], $allChecksPass ? 200 : 206);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
