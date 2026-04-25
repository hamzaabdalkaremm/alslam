<?php

class ErrorHandler
{
    private static bool $debug = false;
    private static string $logPath = '';

    public static function register(array $appConfig): void
    {
        self::$debug = (bool) ($appConfig['debug'] ?? false);
        self::$logPath = $appConfig['log_path'] ?? dirname(__DIR__, 2) . '/storage/logs/app.log';

        $directory = dirname(self::$logPath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        error_reporting(E_ALL);
        ini_set('display_errors', self::$debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::$logPath);

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleException(Throwable $exception): void
    {
        self::log($exception);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            @header('HTTP/1.1 500 Internal Server Error', true, 500);
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = str_contains($uri, '/ajax/') || str_contains($uri, '/api/');

        if ($isApi) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'status' => 'error',
                'message' => self::$debug ? $exception->getMessage() : 'حدث خطأ داخلي في الخادم.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $message = self::$debug ? $exception->getMessage() : 'حدث خطأ داخلي في الخادم. راجع ملف السجل.';
        $details = self::$debug ? ($exception->getFile() . ':' . $exception->getLine()) : basename(self::$logPath);

        echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>خطأ داخلي</title><link rel="stylesheet" href="assets/css/app.css"></head><body><div class="main-content"><div class="card" style="max-width:900px;margin:40px auto;"><h1>خطأ داخلي</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p class="muted">' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</p></div></div></body></html>';
        exit;
    }

    private static function log(Throwable $exception): void
    {
        $message = sprintf(
            "[%s] %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        @file_put_contents(self::$logPath, $message, FILE_APPEND);
    }
}
