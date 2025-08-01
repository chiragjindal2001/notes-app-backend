<?php

namespace Helpers;

// Helper for loading config values from environment variables
class Config {
    private static $envLoaded = false;

    private static function loadEnv() {
        if (!self::$envLoaded) {
            $envFile = dirname(__DIR__) . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        
                        // Remove quotes if present
                        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                            $value = substr($value, 1, -1);
                        }
                        
                        if (!array_key_exists($key, $_ENV)) {
                            $_ENV[$key] = $value;
                        }
                        if (!array_key_exists($key, $_SERVER)) {
                            $_SERVER[$key] = $value;
                        }
                    }
                }
            }
            self::$envLoaded = true;
        }
    }

    public static function get($key, $default = null) {
        self::loadEnv();
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    // Optionally, add a method to get nested config values
    public static function getNested($keys, $default = null) {
        self::loadEnv();
        $value = null;
        foreach ($keys as $key) {
            $value = self::get($key, $default);
            if ($value === $default) {
                return $default;
            }
        }
        return $value;
    }
    
    /**
     * Get database configuration
     * 
     * @return array
     */
    public static function database() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => (int)self::get('DB_PORT', 5432),
            'database' => self::get('DB_DATABASE', 'postgres'),
            'user' => self::get('DB_USERNAME', 'postgres'),
            'password' => self::get('DB_PASSWORD', 'postgres'),
        ];
    }
    
    /**
     * Get JWT configuration
     * 
     * @return array
     */
    public static function jwt() {
        return [
            'secret' => self::get('JWT_SECRET', 'your_secure_jwt_secret'),
            'issuer' => 'php-backend-starter',
            'audience' => 'php-backend-audience',
            'expiration' => 2592000, // 30 days
        ];
    }
    
    /**
     * Get Google OAuth configuration
     * 
     * @return array
     */
    public static function google() {
        return [
            'client_id' => self::get('GOOGLE_CLIENT_ID'),
            'client_secret' => self::get('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => self::get('GOOGLE_REDIRECT_URI'),
        ];
    }
    
    /**
     * Get Razorpay configuration
     * 
     * @return array
     */
    public static function razorpay() {
        return [
            'key_id' => self::get('RAZORPAY_KEY_ID'),
            'key_secret' => self::get('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => self::get('RAZORPAY_WEBHOOK_SECRET'),
        ];
    }
    
    /**
     * Get Email configuration
     * 
     * @return array
     */
    public static function email() {
        return [
            'from' => self::get('EMAIL_FROM'),
            'from_name' => self::get('EMAIL_FROM_NAME'),
            'smtp_host' => self::get('EMAIL_SMTP_HOST'),
            'smtp_port' => (int)self::get('EMAIL_SMTP_PORT', 587),
            'smtp_username' => self::get('EMAIL_SMTP_USERNAME'),
            'smtp_password' => self::get('EMAIL_SMTP_PASSWORD'),
            'smtp_encryption' => self::get('EMAIL_SMTP_ENCRYPTION', 'tls'),
        ];
    }
}
