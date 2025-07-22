<?php
namespace Helpers;

use Exception;

require_once __DIR__ . '/Helpers/JwtService.php';

class AuthHelper
{
    public static function requireAdminAuth()
    {
        $headers = getallheaders();
        // DEBUG: Output all headers for troubleshooting
        file_put_contents(__DIR__ . '/headers_debug.log', print_r($headers, true));
        
        if (!isset($headers['Authorization'])) {
            self::unauthorized('Missing Authorization header');
        }
        
        $auth = $headers['Authorization'];
        if (strpos($auth, 'Bearer ') !== 0) {
            self::unauthorized('Invalid Authorization header');
        }
        
        $token = trim(substr($auth, 7));
        
        try {
            // Initialize JWT helper with config
            JwtService::init();
            
            // Get JWT configuration
            $jwtConfig = Config::jwt();
            $jwt_secret = $jwtConfig['secret'];
            
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                self::unauthorized('Invalid token');
            }
            
            $header = $parts[0];
            $payload = $parts[1];
            $signature = $parts[2];
            
            $expected_sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true)), '+/', '-_'), '=');
            
            // DEBUG: Log the JWT verification process
            file_put_contents(__DIR__ . '/jwt_debug.log', print_r([
                'header' => $header,
                'payload' => $payload,
                'signature' => $signature,
                'expected_sig' => $expected_sig,
                'jwt_secret' => $jwt_secret,
            ], true));
            
            if (!hash_equals($expected_sig, $signature)) {
                self::unauthorized('Invalid token signature');
            }
            
            $payload_data = json_decode(base64_decode($payload), true);
            file_put_contents(__DIR__ . '/admin_jwt_payload.log', print_r($payload_data, true));
            if (!$payload_data || ($payload_data['role'] ?? null) !== 'admin') {
                self::unauthorized('Not an admin');
            }
            
            return $payload_data;
            
        } catch (Exception $e) {
            error_log('Auth error: ' . $e->getMessage());
            file_put_contents(__DIR__ . '/admin_jwt_error.log', $e->getMessage());
            self::unauthorized('Authentication failed: ' . $e->getMessage());
        }
    }
    private static function unauthorized($msg)
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $msg,
            'errors' => [$msg],
            'error_code' => 'UNAUTHORIZED'
        ]);
        exit;
    }
}
