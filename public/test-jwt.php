<?php
// Include Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Include config
require_once __DIR__ . '/../config/config.development.php';

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}
require_once PROJECT_ROOT . '/src/Helpers/JwtService.php';

use Helpers\JwtService;

// Test JWT token generation
$payload = [
    'user_id' => 123,
    'email' => 'test@example.com',
    'name' => 'Test User'
];

$token = JwtService::generateToken(123, 'test@example.com', ['name' => 'Test User']);
echo "Generated Token: " . $token . "\n\n";

// Test JWT token verification
try {
    $decoded = JwtService::validateToken($token);
    echo "Token is valid. Payload: " . print_r($decoded, true) . "\n";
} catch (Exception $e) {
    echo "Token validation failed: " . $e->getMessage() . "\n";
}
