<?php

class Database
{
    private static array $config = [];
    private static ?PDO $connection = null;

    public static function boot(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['driver'],
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );

        self::$connection = new PDO(
            $dsn,
            self::$config['username'],
            self::$config['password'],
            self::$config['options']
        );

        self::$connection->exec('SET NAMES ' . self::$config['charset'] . ' COLLATE ' . self::$config['collation']);

        return self::$connection;
    }
}
