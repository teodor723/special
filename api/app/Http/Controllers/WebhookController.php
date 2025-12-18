<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * AWS Lambda Webhook - Receives HLS URL and Rekognition data from Lambda
     */
    public function awsLambda(Request $request)
    {
        // Log incoming request
        Log::channel('webhook')->info('AWS Lambda Webhook Request', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $request->all(),
        ]);

        // Validate required fields
        $validated = $request->validate([
            'file_name' => 'required|string|max:255',
            'hls_url' => 'required|url',
            'moderation_scores' => 'required',
        ]);

        $fileName = $validated['file_name'];
        $hlsUrl = $validated['hls_url'];
        
        // Process moderation scores
        $moderationScores = $validated['moderation_scores'];
        if (is_array($moderationScores)) {
            $rekognitionJson = json_encode($moderationScores, JSON_UNESCAPED_UNICODE);
        } else {
            // If it's already a JSON string, validate it
            $testDecode = json_decode($moderationScores, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rekognitionJson = $moderationScores;
            } else {
                $rekognitionJson = json_encode(['data' => $moderationScores], JSON_UNESCAPED_UNICODE);
            }
        }

        try {
            // Check if record exists
            $existing = DB::table('reels_aws_upload')
                ->where('file_name', $fileName)
                ->first();

            if ($existing) {
                // Update existing record
                $affected = DB::table('reels_aws_upload')
                    ->where('file_name', $fileName)
                    ->update([
                        'hls_url' => $hlsUrl,
                        'rekognition' => $rekognitionJson,
                    ]);

                $action = 'updated';
            } else {
                // Insert new record
                $s3Url = 'https://s3.' . config('services.aws.default_region', 'eu-west-1') 
                       . '.amazonaws.com/' . config('services.aws.bucket') . '/' . $fileName;

                DB::table('reels_aws_upload')->insert([
                    'file_name' => $fileName,
                    's3_url' => $s3Url,
                    'hls_url' => $hlsUrl,
                    'rekognition' => $rekognitionJson,
                ]);

                $affected = 1;
                $action = 'inserted';
            }

            Log::channel('webhook')->info('AWS Lambda Webhook Success', [
                'file_name' => $fileName,
                'action' => $action,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "HLS URL and Rekognition data {$action} successfully.",
                'file_name' => $fileName,
                'action' => $action,
                'affected_rows' => $affected,
            ]);

        } catch (\Exception $e) {
            Log::channel('webhook')->error('AWS Lambda Webhook Error', [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Database operation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generic webhook endpoint for other services
     */
    public function handle(Request $request, string $service)
    {
        Log::channel('webhook')->info("Webhook received for: {$service}", [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        // Route to specific handler based on service
        return match ($service) {
            'aws-lambda' => $this->awsLambda($request),
            default => response()->json([
                'status' => 'error',
                'message' => 'Unknown webhook service.',
            ], 404),
        };
    }
}

