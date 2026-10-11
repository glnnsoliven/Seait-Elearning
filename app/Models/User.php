<?php
/**
 * User Model
 */
class User extends BaseModel
{
    protected static string $table      = 'users';
    protected static string $primaryKey = 'id';

    public static function findByEmail(string $email): ?array
    {
        return Database::fetchOne("SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1", [$email]);
    }

    public static function findActive(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM users WHERE id=? AND is_active=1 LIMIT 1", [$id]);
    }

    public static function countByRole(string $role): int
    {
        return (int) Database::fetchOne(
            "SELECT COUNT(*) c FROM users WHERE role=? AND is_active=1", [$role])['c'];
    }

    public static function create(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        return Database::insert('users', $data);
    }
}
