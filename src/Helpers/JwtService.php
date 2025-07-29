<?php

namespace Helpers;

use Exception;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class JwtService {
    private static $initialized = false;
    private static $secretKey;
    private static $issuer;
    private static $audience;
    private static $expiration;

    public static function init() {
        if (self::$initialized) {
            return;
        }

        try {
            $jwtConfig = Config::jwt();
            
            if (empty($jwtConfig['secret'])) {
                throw new Exception('JWT secret is not configured');
            }
            
            self::$secretKey = $jwtConfig['secret'];
            self::$issuer = $jwtConfig['issuer'];
            self::$audience = $jwtConfig['audience'];
            self::$expiration = $jwtConfig['expiration'];
            
            self::$initialized = true;
            
        } catch (Exception $e) {
            $error = 'Failed to initialize JwtHelper: ' . $e->getMessage();
            error_log($error);
            throw new Exception($error);
        }
    }

    public static function generateToken($userId, $email, $additionalData = []) {
        try {
            if (empty(self::$secretKey)) {
                throw new \Exception('JWT secret key is not set');
            }
            
            $issuedAt = time();
            $expire = $issuedAt + self::$expiration;

            $data = [
                'iat' => $issuedAt,
                'iss' => self::$issuer,
                'aud' => self::$audience,
                'exp' => $expire,
                'user_id' => $userId,
                'email' => $email
            ];

            // Add any additional data to the token
            if (!empty($additionalData)) {
                $data = array_merge($data, $additionalData);
            }
            
            $token = FirebaseJWT::encode($data, self::$secretKey, 'HS256');
            
            if (empty($token)) {
                throw new \Exception('Failed to generate token');
            }
            
            return $token;
            
        } catch (\Exception $e) {
            $error = 'Failed to generate token: ' . $e->getMessage();
            error_log($error);
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }

    public static function validateToken($token) {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::$secretKey, 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            throw new \Exception('Token has expired');
        } catch (SignatureInvalidException $e) {
            throw new \Exception('Invalid token signature');
        } catch (DomainException | InvalidArgumentException | UnexpectedValueException $e) {
            throw new \Exception('Invalid token');
        }
    }

    public static function getTokenFromHeader() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader)) {
            return null;
        }
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}

