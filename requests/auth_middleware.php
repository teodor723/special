<?php
/**
 * Authentication Middleware for API Endpoints
 * Belloo Dating Site - Security Enhancement
 * Created: December 12, 2025
 */

/**
 * Require user authentication for API access
 * @param array $allowedActions Actions that don't require authentication (e.g., login, register)
 * @return bool True if authenticated or action is allowed
 */
function requireAuth($allowedActions = []) {
    global $sm;
    
    // Get action from request
    $action = '';
    if (isset($_POST['action'])) {
        $action = secureEncode($_POST['action']);
    } elseif (isset($_GET['action'])) {
        $action = secureEncode($_GET['action']);
    }
    
    // Check if action is in allowed list (no auth needed)
    if (!empty($allowedActions) && in_array($action, $allowedActions)) {
        return true;
    }
    
    // Check if user is logged in via session
    if (empty($sm['user']['id']) || !is_numeric($sm['user']['id']) || $sm['user']['id'] <= 0) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'status' => 'error',
            'message' => 'Authentication required. Please login first.',
            'code' => 'AUTH_REQUIRED'
        ]);
        exit;
    }
    
    return true;
}

/**
 * Require admin/moderator role for admin API access
 * @return bool True if user is admin/moderator
 */
function requireAdmin() {
    global $sm, $mysqli;
    
    // Must be logged in first
    requireAuth();
    
    // Check if user has moderator role (column name is 'moderator' not 'admin')
    $userId = (int)$sm['user']['id'];
    $query = $mysqli->query("SELECT moderator FROM users WHERE id = " . $userId . " LIMIT 1");
    
    if ($query && $query->num_rows > 0) {
        $user = $query->fetch_object();
        if ($user->moderator == "Administrator") {
            return true;
        }
    }
    
    // Not admin - deny access
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'status' => 'error',
        'message' => 'Admin/Moderator access required. You do not have permission to perform this action.',
        'code' => 'ADMIN_REQUIRED'
    ]);
    exit;
}

/**
 * Get authenticated user ID from session (never from request parameters)
 * @return int User ID or 0 if not authenticated
 */
function getUserIdFromSession() {
    global $sm;
    
    if (empty($sm['user']['id']) || !is_numeric($sm['user']['id'])) {
        return 0;
    }
    
    return (int)$sm['user']['id'];
}

/**
 * Verify cron job access with IP whitelist and secret token
 * @param string $requiredToken Secret token for cron authentication
 * @return bool True if authorized
 */
function requireCronAuth($requiredToken) {
    // Allowed IPs for cron jobs (localhost and server IP)
    $allowedIPs = [
        '127.0.0.1',
        '::1',
        $_SERVER['SERVER_ADDR'] ?? ''
    ];
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check IP whitelist
    if (in_array($clientIP, $allowedIPs)) {
        return true;
    }
    
    // Check secret token
    if (isset($_GET['token']) && $_GET['token'] === $requiredToken) {
        return true;
    }
    
    // Access denied
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'status' => 'error',
        'message' => 'Access denied. Cron job authentication required.',
        'code' => 'CRON_AUTH_REQUIRED'
    ]);
    exit;
}

/**
 * Validate that user owns the resource they're trying to access
 * @param int $resourceUserId User ID associated with the resource
 * @param int $currentUserId Current logged-in user ID
 * @return bool True if user owns resource or is admin
 */
function requireOwnership($resourceUserId, $currentUserId = null) {
    global $sm, $mysqli;
    
    if ($currentUserId === null) {
        $currentUserId = getUserIdFromSession();
    }
    
    // Check if user owns the resource
    if ((int)$resourceUserId === (int)$currentUserId) {
        return true;
    }
    
    // Check if user is admin/moderator (moderators can access anything)
    $query = $mysqli->query("SELECT moderator FROM users WHERE id = " . (int)$currentUserId . " LIMIT 1");
    if ($query && $query->num_rows > 0) {
        $user = $query->fetch_object();
        if ($user->moderator == "Administrator") {
            return true;
        }
    }
    
    // Access denied
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'status' => 'error',
        'message' => 'Access denied. You do not own this resource.',
        'code' => 'OWNERSHIP_REQUIRED'
    ]);
    exit;
}

/**
 * Log API access for security audit
 * @param string $endpoint Endpoint accessed
 * @param string $action Action performed
 * @param int $userId User ID (0 if not authenticated)
 */
function logApiAccess($endpoint, $action = '', $userId = 0) {
    global $mysqli;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $timestamp = time();
    
    // Log to database (create this table if it doesn't exist)
    $mysqli->query("INSERT INTO api_access_logs (user_id, endpoint, action, ip_address, user_agent, method, timestamp) 
                    VALUES ($userId, '$endpoint', '$action', '$ip', '$userAgent', '$method', $timestamp)");
}
