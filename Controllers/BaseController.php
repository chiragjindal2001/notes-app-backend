<?php

require_once __DIR__ . '/../src/Helpers/Config.php';

class BaseController {
    protected $config;

    public function __construct() {
        // Load configuration using environment variables
        $this->config = [
            'APP_DEBUG' => \Helpers\Config::get('APP_DEBUG', true),
            'jwt_secret' => \Helpers\Config::get('JWT_SECRET', 'your_secure_jwt_secret'),
            'base_url' => \Helpers\Config::get('BASE_URL', 'http://localhost:3000'),
            'db' => \Helpers\Config::database(),
            'google' => \Helpers\Config::google(),
            'razorpay' => \Helpers\Config::razorpay(),
            'email' => \Helpers\Config::email(),
        ];
    }

    /**
     * Send JSON response
     */
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get request input
     */
    protected function getInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        return $input ?: [];
    }

    /**
     * Get bearer token from header
     */
    public function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        // Fallback: check for user_token in cookies
        if (isset($_COOKIE['user_token']) && !empty($_COOKIE['user_token'])) {
            return $_COOKIE['user_token'];
        }
        return null;
    }

    /**
     * Get authorization header
     */
    private function getAuthorizationHeader() {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }
}
