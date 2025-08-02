<?php

namespace Helpers;

class Database {
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            $dbConfig = Config::database();
            
            self::$connection = mysqli_connect(
                $dbConfig['host'],
                $dbConfig['user'],
                $dbConfig['password'],
                $dbConfig['database'],
                $dbConfig['port']
            );
            
            if (!self::$connection) {
                error_log('Database connection failed: ' . mysqli_connect_error());
                throw new Exception('Failed to connect to database');
            }
            
            // Set charset to utf8mb4
            mysqli_set_charset(self::$connection, 'utf8mb4');
        }
        
        return self::$connection;
    }
    
    public static function closeConnection() {
        if (self::$connection !== null) {
            mysqli_close(self::$connection);
            self::$connection = null;
        }
    }
}
