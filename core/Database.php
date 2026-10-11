<?php
/**
 * SEAIT E-Learning — Database Singleton (PDO)
 */

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Return the shared PDO connection.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('A database error occurred. Please try again later.');
            }
        }

        return self::$instance;
    }

    /**
     * Shorthand: prepare + execute, return the statement.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all rows.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Insert a row and return the last insert ID.
     */
    public static function insert(string $table, array $data): int
    {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `{$table}` ({$cols}) VALUES ({$places})", array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Update rows and return affected count.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
        $set      = implode(', ', $setParts);
        $stmt     = self::query(
            "UPDATE `{$table}` SET {$set} WHERE {$where}",
            array_merge(array_values($data), $whereParams)
        );
        return $stmt->rowCount();
    }

    /**
     * Delete rows and return affected count.
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /**
     * Roll back the current transaction.
     */
    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }
}
