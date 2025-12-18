<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Allowed file types for upload
     */
    private array $allowedMimes = [
        // Images
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        // Videos
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogg',
        'video/quicktime' => 'mov',
    ];

    /**
     * Max file sizes (in bytes)
     */
    private int $maxImageSize = 20 * 1024 * 1024; // 20MB
    private int $maxVideoSize = 100 * 1024 * 1024; // 100MB

    /**
     * Upload file to S3
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        // Validate file type
        if (!array_key_exists($mimeType, $this->allowedMimes)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File type not allowed.',
            ], 400);
        }

        // Check file size
        $isVideo = str_starts_with($mimeType, 'video/');
        $maxSize = $isVideo ? $this->maxVideoSize : $this->maxImageSize;
        
        if ($file->getSize() > $maxSize) {
            $maxMB = $maxSize / (1024 * 1024);
            return response()->json([
                'status' => 'error',
                'message' => "File size exceeds {$maxMB}MB limit.",
            ], 400);
        }

        try {
            // Generate unique filename
            $extension = $this->allowedMimes[$mimeType];
            $filename = time() . '_' . Str::random(10) . '.' . $extension;

            // Upload to S3
            $path = Storage::disk('s3')->putFileAs('uploads', $file, $filename, 'public');

            if (!$path) {
                throw new \Exception('Failed to upload file to S3');
            }

            // Get the full S3 URL
            $url = Storage::disk('s3')->url($path);

            // Build response
            $response = [
                'status' => 'ok',
                'path' => $url,
                'filename' => $filename,
                'video' => $isVideo ? 1 : 0,
            ];

            // Generate thumbnail for images
            if (!$isVideo) {
                $thumbFilename = 'thumb_' . $filename;
                $thumbPath = $this->createThumbnail($file, $thumbFilename);
                
                if ($thumbPath) {
                    $response['thumb'] = Storage::disk('s3')->url($thumbPath);
                } else {
                    $response['thumb'] = $url; // Fallback to original
                }
            } else {
                $response['thumb'] = '';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create thumbnail for image
     */
    private function createThumbnail($file, string $thumbFilename, int $width = 200): ?string
    {
        try {
            $sourcePath = $file->getRealPath();
            $mimeType = $file->getMimeType();
            
            // Create image resource based on type
            $source = match ($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($sourcePath),
                'image/png' => imagecreatefrompng($sourcePath),
                'image/webp' => imagecreatefromwebp($sourcePath),
                'image/gif' => imagecreatefromgif($sourcePath),
                default => null,
            };

            if (!$source) {
                return null;
            }

            // Handle EXIF orientation for JPEG
            if ($mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($sourcePath);
                if (!empty($exif['Orientation'])) {
                    $source = match ($exif['Orientation']) {
                        3 => imagerotate($source, 180, 0),
                        6 => imagerotate($source, -90, 0),
                        8 => imagerotate($source, 90, 0),
                        default => $source,
                    };
                }
            }

            // Calculate dimensions
            $origWidth = imagesx($source);
            $origHeight = imagesy($source);
            $height = (int) floor($origHeight * ($width / $origWidth));

            // Create thumbnail
            $thumb = imagecreatetruecolor($width, $height);
            
            // Preserve transparency for PNG/GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
                imagefill($thumb, 0, 0, $transparent);
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);

            // Save to temp file
            $tempPath = sys_get_temp_dir() . '/' . $thumbFilename;
            imagejpeg($thumb, $tempPath, 85);

            // Upload thumbnail to S3
            $thumbPath = Storage::disk('s3')->putFileAs('uploads', new \Illuminate\Http\File($tempPath), $thumbFilename, 'public');

            // Cleanup
            imagedestroy($source);
            imagedestroy($thumb);
            @unlink($tempPath);

            return $thumbPath;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete file from S3
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');
            
            // Extract filename from URL
            $filename = basename(parse_url($path, PHP_URL_PATH));
            $storagePath = 'uploads/' . $filename;

            if (Storage::disk('s3')->exists($storagePath)) {
                Storage::disk('s3')->delete($storagePath);
                
                // Also try to delete thumbnail
                $thumbPath = 'uploads/thumb_' . $filename;
                if (Storage::disk('s3')->exists($thumbPath)) {
                    Storage::disk('s3')->delete($thumbPath);
                }
            }

            return response()->json([
                'status' => 'ok',
                'message' => 'File deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

