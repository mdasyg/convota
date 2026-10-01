<?php
declare(strict_types=1);

namespace Convota\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * High-performance SQLite PDO connection manager
 * Enforces WAL mode, foreign keys, busy timeout, and immediate transactions.
 */
class Database
{
    private static ?self $instance = null;
    private PDO $pdo;
    private string $dbPath;

    public function __construct(string $dbPath, int $busyTimeout = 5000)
    {
        $this->dbPath = $dbPath;
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create database directory: {$dir}");
            }
        }

        $dsn = "sqlite:{$dbPath}";
        try {
            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Enforce SQLite WAL mode and foreign key constraints
            $this->pdo->exec("PRAGMA journal_mode = WAL;");
            $this->pdo->exec("PRAGMA foreign_keys = ON;");
            $this->pdo->exec("PRAGMA busy_timeout = {$busyTimeout};");
            $this->pdo->exec("PRAGMA synchronous = NORMAL;");
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function getInstance(?string $customPath = null): self
    {
        if ($customPath !== null) {
            return new self($customPath);
        }

        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $path = $config['database']['path'];
            $timeout = $config['database']['timeout'] ?? 5000;
            self::$instance = new self($path, $timeout);
        }

        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private int $transactionLevel = 0;

    /**
     * Standard transaction begin with savepoint support
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $res = $this->pdo->beginTransaction();
            $this->transactionLevel = 1;
            return $res;
        }

        $this->transactionLevel++;
        $this->pdo->exec("SAVEPOINT TRANS_LEVEL_{$this->transactionLevel};");
        return true;
    }

    /**
     * Begin an IMMEDIATE transaction in SQLite.
     * Prevents race conditions during ballot casting by acquiring a write lock upfront.
     */
    public function beginTransactionImmediate(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->exec("BEGIN IMMEDIATE TRANSACTION;");
            $this->transactionLevel = 1;
            return;
        }

        $this->transactionLevel++;
        $this->pdo->exec("SAVEPOINT TRANS_LEVEL_{$this->transactionLevel};");
    }

    public function commit(): bool
    {
        if ($this->transactionLevel <= 0) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $res = $this->pdo->commit();
            $this->transactionLevel = 0;
            return $res;
        }

        $this->pdo->exec("RELEASE SAVEPOINT TRANS_LEVEL_{$this->transactionLevel};");
        $this->transactionLevel--;
        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionLevel <= 0) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $res = $this->pdo->rollBack();
            $this->transactionLevel = 0;
            return $res;
        }

        $this->pdo->exec("ROLLBACK TO SAVEPOINT TRANS_LEVEL_{$this->transactionLevel};");
        $this->transactionLevel--;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0 || $this->pdo->inTransaction();
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $col = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($col);
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
