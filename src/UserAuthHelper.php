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

            // Add padding to base64 if needed
            $payload = str_pad($payload, strlen($payload) % 4, '=', STR_PAD_RIGHT);
            $payload_data = json_decode(base64_decode($payload), true);
            if (!$payload_data) {
                error_log('Failed to decode JWT payload: ' . $payload);
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
            $jwt_secret = \Helpers\Config::get('JWT_SECRET', 'changeme');

            // Validate JWT token manually (same format as login)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new Exception('Invalid token format');
            }

            $header = $parts[0];
            $payload = $parts[1];
            $signature = $parts[2];

            $expected_sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true)), '+/', '-_'), '=');
            
            if (!hash_equals($expected_sig, $signature)) {
                throw new Exception('Invalid token signature');
            }

            // Add padding to base64 if needed
            $payload = str_pad($payload, strlen($payload) % 4, '=', STR_PAD_RIGHT);
            $payload_data = json_decode(base64_decode($payload), true);
            if (!$payload_data) {
                error_log('Failed to decode JWT payload in validateJWT: ' . $payload);
                throw new Exception('Invalid token payload');
            }
            
            if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
                throw new Exception('Token expired');
            }

            if (!isset($payload_data['user_id'])) {
                throw new Exception('Invalid token payload: missing user_id');
            }
            
            return $payload_data;
            
        } catch (\Exception $e) {
            error_log('JWT validation failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function generateToken($user_data, $expiry_hours = 24)
    {
        $jwt_secret = \Helpers\Config::get('JWT_SECRET', 'changeme');
        
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
