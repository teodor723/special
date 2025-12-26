<?php

namespace App\Traits;

trait ModeratesContent
{
    /**
     * Moderate content based on Rekognition results
     * Uses score-based moderation matching the logic from requests/belloo.php
     * 
     * @param array $rekognitionData The rekognition data with moderation scores
     * @return int 1 = Approved, 0 = Pending, 2 = Rejected
     */
    protected function moderateContent(array $rekognitionData): int
    {
        // Extract moderation scores
        $nudity = isset($rekognitionData['nudity']) ? (float)$rekognitionData['nudity'] : 0;
        $sexual = isset($rekognitionData['sexual']) ? (float)$rekognitionData['sexual'] : 0;
        $violence = isset($rekognitionData['violence']) ? (float)$rekognitionData['violence'] : 0;
        $other = isset($rekognitionData['other']) ? (float)$rekognitionData['other'] : 0;
        
        $maxScore = max($nudity, $sexual, $violence, $other);
        
        // Determine visibility based on max score
        if ($maxScore >= 80) {
            return 2; // Rejected - violates policy
        } else if ($maxScore >= 60) {
            return 0; // Pending - needs manual review
        } else {
            return 1; // Approved - safe content
        }
    }
}

