<?php

namespace Helpers;

use Exception;

class UserAuthHelper
{
    public static function requireUserAuth()
    {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            self::unauthorized('Authentication required');
        }

        $token = $matches[1];
        
        try {
            // Initialize JWT helper with config
            JwtService::init();
            
            // Get JWT configuration
            $jwtConfig = Config::jwt();
            $jwt_secret = $jwtConfig['secret'];

            // Validate JWT token
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                self::unauthorized('Invalid token');
            }

            $header = $parts[0];
            $payload = $parts[1];
            $signature = $parts[2];

            $expected_sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true)), '+/', '-_'), '=');
            
            if (!hash_equals($expected_sig, $signature)) {
                self::unauthorized('Invalid token signature');
            }

            $payload_data = json_decode(base64_decode($payload), true);
            if (!$payload_data) {
                self::unauthorized('Invalid token payload');
            }
            
            if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
                self::unauthorized('Token expired');
            }

            return $payload_data; // Return user data from token
            
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            self::unauthorized('Authentication failed: ' . $e->getMessage());
        }
    }

    public static function getCurrentUserId()
    {
        $user = self::requireUserAuth();
        return $user['user_id'] ?? null;
    }
    
    private static function unauthorized($message)
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }

    public static function validateJWT($token)
    {
        try {
            // Ensure we have the JwtHelper class
            if (!class_exists(\Helpers\JwtService::class)) {
                throw new Exception('JwtService class not found');
            }
            
            // Use the fully qualified namespace with error handling
            $decoded = \Helpers\JwtService::validateToken($token);
            
            if (!$decoded || (!isset($decoded['user_id']) && !isset($decoded['sub']))) {
                throw new Exception('Invalid token payload');
            }
            
            return $decoded;
            
        } catch (\Firebase\JWT\ExpiredException $e) {
            error_log('JWT Token expired: ' . $e->getMessage());
            return false;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            error_log('JWT Signature verification failed: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('JWT validation failed: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    public static function generateToken($user_data, $expiry_hours = 24)
    {
        $config = require dirname(__DIR__) . '/config/config.development.php';
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => (int)$user_data['id'],
            'email' => $user_data['email'],
            'name' => $user_data['name'],
            'exp' => time() + ($expiry_hours * 60 * 60)
        ]);

        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header_encoded.$payload_encoded", $jwt_secret, true)), '+/', '-_'), '=');
        
        return "$header_encoded.$payload_encoded.$signature";
    }
}
