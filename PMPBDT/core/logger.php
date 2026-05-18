<?php
/**
 * ============================================================
 * ERROR LOGGER
 * ============================================================
 * Quản lý ghi log lỗi và xử lý ngoại lệ tập trung.
 */

class Logger {
    private static string $logPath = __DIR__ . '/../storage/logs/app.log';

    /**
     * Ghi nội dung vào file log
     */
    public static function log(string $message, string $level = 'ERROR'): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents(self::$logPath, $logMessage, FILE_APPEND);
    }

    /**
     * Xử lý lỗi hệ thống (PHP Errors)
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): void {
        // Tôn trọng toán tử tắt cảnh báo @
        if (!(error_reporting() & $errno)) {
            return;
        }

        $message = "Error [$errno]: $errstr in $errfile on line $errline";
        self::log($message, 'PHP_ERROR');
        
        // Trên môi trường production, không hiển thị lỗi chi tiết
        if (ini_get('display_errors') === '0') {
            echo "<h1>Rất tiếc, đã có lỗi xảy ra.</h1><p>Vui lòng thử lại sau.</p>";
            exit;
        }
    }

    /**
     * Xử lý ngoại lệ chưa được bắt (Exceptions)
     */
    public static function handleException(Throwable $e): void {
        $message = "Exception: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString();
        self::log($message, 'EXCEPTION');

        if (ini_get('display_errors') === '0') {
            echo "<h1>Đã xảy ra lỗi hệ thống.</h1><p>Chúng tôi đã ghi nhận và sẽ khắc phục sớm.</p>";
            exit;
        }
    }
}

// Thiết lập Error Handler
set_error_handler(['Logger', 'handleError']);
set_exception_handler(['Logger', 'handleException']);
