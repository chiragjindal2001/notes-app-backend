<?php
namespace Helpers;

class Logger
{
    private static $logFile;
    private static $instance = null;
    
    private function __construct()
    {
        // Create logs directory if it doesn't exist
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set log file path
        self::$logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function logRequest($requestData)
    {
        $log = self::formatLogEntry('REQUEST', $requestData);
        self::writeToLog($log);
    }
    
    public static function logResponse($responseData)
    {
        $log = self::formatLogEntry('RESPONSE', $responseData);
        self::writeToLog($log);
    }
    
    public static function logError($errorData)
    {
        $log = self::formatLogEntry('ERROR', $errorData);
        self::writeToLog($log);
    }
    
    private static function formatLogEntry($type, $data)
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $log = "[$timestamp] [$type] [IP: $ip] [$method $uri]\n";
        
        if (is_array($data) || is_object($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log .= $data . "\n";
        }
        
        return $log . str_repeat('-', 80) . "\n\n";
    }
    
    private static function writeToLog($message)
    {
        // Ensure instance is initialized
        if (self::$instance === null) {
            self::getInstance();
        }
        
        // Write to log file
        file_put_contents(
            self::$logFile,
            $message,
            FILE_APPEND | LOCK_EX
        );
    }
}
