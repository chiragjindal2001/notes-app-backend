<?php

namespace Helpers;

class Database {
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            $dbConfig = Config::database();
            
            $connectionString = sprintf(
                "host=%s port=%d dbname=%s user=%s password=%s",
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['database'],
                $dbConfig['user'],
                $dbConfig['password']
            );
            
            self::$connection = pg_connect($connectionString);
            
            if (!self::$connection) {
                error_log('Database connection failed: ' . pg_last_error());
                throw new Exception('Failed to connect to database');
            }
        }
        
        return self::$connection;
    }
    
    public static function closeConnection() {
        if (self::$connection !== null) {
            pg_close(self::$connection);
            self::$connection = null;
        }
    }
}
