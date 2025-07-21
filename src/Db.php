<?php
class Db
{
    private static $conn = null;

    public static function getConnection($config)
    {
        if (self::$conn === null) {
            $connStr = sprintf(
                "host=%s port=%d dbname=%s user=%s password=%s",
                $config['db']['host'],
                $config['db']['port'],
                $config['db']['database'],
                $config['db']['user'],
                $config['db']['password']
            );
            self::$conn = pg_connect($connStr);
            if (!self::$conn) {
                throw new Exception('Failed to connect to PostgreSQL: ' . pg_last_error());
            }
        }
        return self::$conn;
    }
}
