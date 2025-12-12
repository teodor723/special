<?php
/* Belloo Software by https://premiumdatingscript.com */
/* AWS Lambda Webhook - Receives HLS URL and Rekognition data from Lambda */

header('Content-Type: application/json');
require_once('../assets/includes/core.php');

// Logging function
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/../assets/logs/aws-lambda-webhook.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if ($data !== null) {
        $logEntry .= "\n" . print_r($data, true);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Only POST requests are accepted.']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');

// Log incoming request
logWebhook('=== NEW WEBHOOK REQUEST ===', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
    'raw_input_length' => strlen($input),
    'raw_input_preview' => substr($input, 0, 500) . (strlen($input) > 500 ? '...' : '')
]);

$data = json_decode($input, true);

// Validate input
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data.']);
    exit();
}

// Required fields
$requiredFields = ['file_name', 'hls_url', 'moderation_scores'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

// Sanitize inputs
$file_name = $mysqli->real_escape_string($data['file_name']);
$hls_url = $mysqli->real_escape_string($data['hls_url']);

// Extract only moderation_scores for rekognition field
$moderationScores = $data['moderation_scores'];
if (is_array($moderationScores)) {
    $rekognitionJson = json_encode($moderationScores, JSON_UNESCAPED_UNICODE);
} else {
    // If it's already a JSON string, validate it
    $testDecode = json_decode($moderationScores, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $rekognitionJson = $moderationScores;
    } else {
        // If invalid, wrap it
        $rekognitionJson = json_encode(['data' => $moderationScores], JSON_UNESCAPED_UNICODE);
    }
}
$rekognition = $mysqli->real_escape_string($rekognitionJson);

// Validate URL format
if (!filter_var($hls_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid HLS URL format.']);
    exit();
}

// Check if record exists
$checkQuery = "SELECT file_name FROM reels_aws_upload WHERE file_name = '$file_name' LIMIT 1";
$checkResult = $mysqli->query($checkQuery);

if (!$checkResult) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database query failed.',
        'message' => $mysqli->error
    ]);
    exit();
}

$isUpdate = ($checkResult->num_rows > 0);
$affectedRows = 0;

if ($isUpdate) {
    // Update existing record
    $query = "UPDATE reels_aws_upload 
              SET hls_url = '$hls_url', 
                  rekognition = '$rekognition' 
              WHERE file_name = '$file_name'";
    
    $result = $mysqli->query($query);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database update failed.',
            'message' => $mysqli->error
        ]);
        exit();
    }
    
    $affectedRows = $mysqli->affected_rows;
    $action = 'updated';
} else {
    // Insert new record (s3_url will be empty since it's not in the payload)
    $s3_url = 'https://s3.eu-west-1.amazonaws.com/special-dating/'.$file_name;
    $query = "INSERT INTO reels_aws_upload (file_name, s3_url, hls_url, rekognition) 
              VALUES ('$file_name', '$s3_url', '$hls_url', '$rekognition')";
    
    $result = $mysqli->query($query);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database insert failed.',
            'message' => $mysqli->error
        ]);
        exit();
    }
    
    $affectedRows = $mysqli->affected_rows;
    $action = 'inserted';
}

// Success response
echo json_encode([
    'status' => 'success',
    'message' => "HLS URL and Rekognition data $action successfully.",
    'file_name' => $data['file_name'],
    'action' => $action,
    'affected_rows' => $affectedRows
]);

