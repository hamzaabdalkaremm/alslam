<?php

class CSRF
{
    public static function token(): string
    {
        $token = Session::get('_csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf_token', $token);
        }

        return $token;
    }

    public static function validate(?string $token): bool
    {
        return $token && hash_equals((string) Session::get('_csrf_token'), $token);
    }

    public static function verifyRequest(): void
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate($token)) {
            Response::error('رمز الحماية غير صالح.', 419);
        }
    }
}
