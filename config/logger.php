<?php
// Define the path to the log file within the 'logs' directory at the project root
define('LOG_FILE_PATH', __DIR__ . '/../logs/app_error.log');

/**
 * Custom function to write formatted messages to the log file.
 */
function write_error_log($message, $level = 'INFO') {
    // 1. Ensure the logs directory exists first
    $logDir = dirname(LOG_FILE_PATH);
    if (!file_exists($logDir)) {
        // Try to create it if it doesn't exist (permissions permitting)
        mkdir($logDir, 0755, true);
    }

    // 2. Prepare the log entry details
    $timestamp = date('Y-m-d H:i:s');
    // Add current User ID to the log if they are logged in, helps with debugging
    $userIdTag = isset($_SESSION['user_id']) ? '[User ID: ' . $_SESSION['user_id'] . ']' : '[Guest]';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
    $requestURI = $_SERVER['REQUEST_URI'] ?? 'Unknown URI';

    // 3. Format the final message line
    $formattedMessage = "[$timestamp] [$level] $userIdTag [IP: $clientIP] [URI: $requestURI] $message" . PHP_EOL;

    // 4. Write to the file using LOCK_EX to prevent write collisions
    // FILE_APPEND adds to the end of the file instead of overwriting it
    file_put_contents(LOG_FILE_PATH, $formattedMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Handler for Uncaught Exceptions (e.g., try/catch blocks that were missed)
 */
function customExceptionHandler($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage();
    $message .= " in file " . $exception->getFile() . " on line " . $exception->getLine();
    $message .= "\nStack trace:\n" . $exception->getTraceAsString();

    write_error_log($message, 'CRITICAL');

    // If not in development mode, show a generic error page and stop.
    if (ini_get('display_errors') == 0) {
        // You could include a pretty 500 error page here
        die("A critical system error occurred. The incident has been logged.");
    }
}

/**
 * Handler for PHP Errors (Warnings, Notices, Deprecated, etc.)
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Don't log errors suppressed by the @ operator or not included in error_reporting()
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorType = match ($errno) {
    E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
        E_NOTICE, E_USER_NOTICE => 'NOTICE',
        E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
        default => 'PHP ERROR',
    };

    $message = "$errstr in $errfile on line $errline";
    write_error_log($message, $errorType);

    // Don't stop execution for warnings/notices, let PHP continue.
    return false;
}

/**
 * Shutdown function to catch Fatal Errors (like parse errors or out of memory)
 */
register_shutdown_function(function () {
    $error = error_get_last();
    // If the script ended because of a fatal error type
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = "Fatal Error: {$error['message']} in file {$error['file']} on line {$error['line']}";
        write_error_log($message, 'FATAL');

        // If display_errors is off, ensure the user doesn't see a half-rendered page
        if (ini_get('display_errors') == 0) {
            if (!headers_sent()) { header("HTTP/1.1 500 Internal Server Error"); }
            echo "A critical system error occurred. Please contact support.";
        }
    }
});

// --- Register the handlers defined above ---
set_exception_handler('customExceptionHandler');
set_error_handler('customErrorHandler');
?>
