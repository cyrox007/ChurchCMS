<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseManager
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = (string) Config::get('database.driver', 'pgsql');
        $host = (string) Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', $driver === 'pgsql' ? 5432 : 3306);
        $database = (string) Config::get('database.database', '');
        $username = (string) Config::get('database.username', '');
        $password = (string) Config::get('database.password', '');

        $dsn = match ($driver) {
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return $this->pdo;
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
