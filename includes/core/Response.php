<?php

class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message, array $data = []): never
    {
        self::json(['status' => 'success', 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $status = 422, array $errors = []): never
    {
        self::json(['status' => 'error', 'message' => $message, 'errors' => $errors], $status);
    }
}
