<?php
class Db
{
    private static $conn = null;

    public static function getConnection($config)
    {
        if (self::$conn === null) {
            self::$conn = mysqli_connect(
                $config['db']['host'],
                $config['db']['user'],
                $config['db']['password'],
                $config['db']['database'],
                $config['db']['port'] ?? 3306
            );
            if (!self::$conn) {
                throw new Exception('Failed to connect to MySQL: ' . mysqli_connect_error());
            }
        }
        return self::$conn;
    }
}
