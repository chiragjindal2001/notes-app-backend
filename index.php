<?php
// Remove Dotenv usage and .env loading
define('PROJECT_ROOT', __DIR__);
require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/config/config.development.php';
require_once PROJECT_ROOT . '/src/Helpers/Config.php';
require_once PROJECT_ROOT . '/src/UserAuthHelper.php';
require_once PROJECT_ROOT . '/src/Helpers/JwtService.php';

// --- CORS CONFIGURATION ---
// List of allowed origins
$allowedOrigins = [
    'https://civilstudies.vercel.app',
    // Add other allowed origins here if needed
];

// Get the origin of the request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if the origin is in the allowed list
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

// Required headers for CORS with credentials
header('Access-Control-Allow-Origin: https://civilstudies.vercel.app');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set timezone
date_default_timezone_set('UTC');

// Error reporting
if (\Helpers\Config::get('APP_DEBUG') === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start output buffering for response logging
ob_start();

// Log the incoming request
$requestData = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'query' => $_GET ?? [],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Initialize logger
require_once __DIR__ . '/src/Helpers/Logger.php';
\Helpers\Logger::getInstance();
\Helpers\Logger::logRequest($requestData);

// Error handler to log errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error = [
        'type' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
    ];
    \Helpers\Logger::logError($error);
    return false; // Let the default error handler run
});

// Exception handler to log exceptions
set_exception_handler(function($exception) {
    $error = [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];
    \Helpers\Logger::logError($error);
    
    http_response_code(500);
    if (\Helpers\Config::get('APP_DEBUG') === true) {
        echo json_encode([
            'success' => false,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred'
        ]);
    }
    exit;
});

// Custom autoloader for our application
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefixes = [
        'App\\' => '/src/',
        'Helpers\\' => '/src/Helpers/',
        'Services\\' => '/src/Services/'
    ];
    
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            // Get the relative class name
            $relativeClass = substr($class, $len);
            
            // Replace the namespace prefix with the base directory, replace namespace
            // separators with directory separators, and append with .php
            $file = PROJECT_ROOT . $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// Initialize JWT helper
\Helpers\JwtService::init();

// Delegate all routing logic to routes.php
require __DIR__ . '/routes.php';

// Log the response
$response = ob_get_clean();

// Log the response data
$responseData = [
    'status' => http_response_code(),
    'headers' => headers_list(),
    'body' => $response
];

\Helpers\Logger::logResponse($responseData);

// Output the response
echo $response;
