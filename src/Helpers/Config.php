<?php

namespace Helpers;

// Helper for loading config values from config.development.php
class Config {
    private static $config = null;

    public static function get($key, $default = null) {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/../config/config.development.php';
        }
        return self::$config[$key] ?? $default;
    }

    // Optionally, add a method to get nested config values
    public static function getNested($keys, $default = null) {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/../config/config.development.php';
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
        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/../config/config.development.php';
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
            self::$config = require dirname(__DIR__) . '/../config/config.development.php';
        }
        return self::$config['google'];
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
