<?php
// Simple JWT debug endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once PROJECT_ROOT . '/src/UserAuthHelper.php';

$response = [
    'success' => false,
    'message' => '',
    'debug_info' => []
];

try {
    // Get token from Authorization header
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    $response['debug_info']['auth_header_present'] = !empty($auth_header);
    $response['debug_info']['auth_header'] = $auth_header;
    
    if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $response['message'] = 'No valid Authorization header found';
        echo json_encode($response);
        exit;
    }

    $token = $matches[1];
    $response['debug_info']['token_length'] = strlen($token);
    $response['debug_info']['token_start'] = substr($token, 0, 20) . '...';
    
    // Validate JWT
    $user = \Helpers\UserAuthHelper::validateJWT($token);
    
    $response['success'] = true;
    $response['message'] = 'JWT validation successful';
    $response['user'] = $user;
    $response['debug_info']['user_id'] = $user['user_id'] ?? 'not_found';
    
} catch (Exception $e) {
    $response['message'] = 'JWT validation failed: ' . $e->getMessage();
    $response['debug_info']['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT); 