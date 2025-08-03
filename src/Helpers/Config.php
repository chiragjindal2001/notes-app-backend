<?php

namespace Helpers;

// Helper for loading config values from config.development.php
class Config {
    private static $config = null;

    public static function get($key, $default = null) {
        if (self::$config === null) {
            self::$config = require PROJECT_ROOT . '/config/config.development.php';
        }
        
        // Check environment variable first
        $envKey = strtoupper($key);
        if (isset($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }
        
        return self::$config[$key] ?? $default;
    }

    // Optionally, add a method to get nested config values
    public static function getNested($keys, $default = null) {
        if (self::$config === null) {
            self::$config = require PROJECT_ROOT . '/config/config.development.php';
        }
        $value = self::$config;
        foreach ($keys as $key) {
            if (isset($value[$key])) {
                $value = $value[$key];
            } else {
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
        if (self::$config === null) {
            self::$config = require PROJECT_ROOT . '/config/config.development.php';
        }
        
        // Check if using the new 'db' array structure
        if (isset(self::$config['db'])) {
            return self::$config['db'];
        }
        
        // Fallback to individual keys for backward compatibility
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => (int)self::get('DB_PORT', 3306), // Changed default to MySQL port
            'database' => self::get('DB_DATABASE', 'mysql'),
            'user' => self::get('DB_USERNAME', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
        ];
    }
    
    /**
     * Get JWT configuration
     * 
     * @return array
     */
    public static function jwt() {
        if (self::$config === null) {
            self::$config = require PROJECT_ROOT . '/config/config.development.php';
        }
        return [
            'secret' => self::$config['jwt_secret'] ?? 'your_secure_jwt_secret',
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
        if (self::$config === null) {
            self::$config = require PROJECT_ROOT . '/config/config.development.php';
        }
        
        // Use environment variables if available, otherwise fall back to config
        return [
            'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? self::$config['google']['client_id'],
            'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? self::$config['google']['client_secret'],
            'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? self::$config['google']['redirect_uri'],
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
        ];
    }
}
