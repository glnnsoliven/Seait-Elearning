<?php
/**
 * SEAIT E-Learning — Base Model
 *
 * Lightweight active-record style base class.
 * All model classes extend this to get CRUD helpers.
 */
abstract class BaseModel
{
    protected static string $table      = '';
    protected static string $primaryKey = 'id';

    // ── CRUD ─────────────────────────────────────────────────

    public static function find(int $id): ?array
    {
        $t  = static::$table;
        $pk = static::$primaryKey;
        return Database::fetchOne("SELECT * FROM `{$t}` WHERE `{$pk}`=? LIMIT 1", [$id]);
    }

    public static function all(string $orderBy = ''): array
    {
        $t   = static::$table;
        $sql = "SELECT * FROM `{$t}`";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        return Database::fetchAll($sql);
    }

    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    public static function update(int $id, array $data): void
    {
        $pk = static::$primaryKey;
        Database::update(static::$table, $data, "`{$pk}`=?", [$id]);
    }

    public static function delete(int $id): void
    {
        $pk = static::$primaryKey;
        Database::delete(static::$table, "`{$pk}`=?", [$id]);
    }

    public static function count(string $where = '', array $params = []): int
    {
        $t   = static::$table;
        $sql = "SELECT COUNT(*) c FROM `{$t}`";
        if ($where) $sql .= " WHERE {$where}";
        return (int)(Database::fetchOne($sql, $params)['c'] ?? 0);
    }

    public static function exists(string $where, array $params): bool
    {
        $t   = static::$table;
        $row = Database::fetchOne("SELECT `" . static::$primaryKey . "` FROM `{$t}` WHERE {$where} LIMIT 1", $params);
        return $row !== null;
    }

    // ── Soft delete ───────────────────────────────────────────

    public static function softDelete(int $id): void
    {
        static::update($id, ['is_active' => 0]);
    }
}
