<?php
/**
 * Category.php
 * Added in Phase 4 (see handoff doc Section 3) so each material category
 * owns its own item-code prefix, instead of guessing a prefix from a
 * free-text category name. Supports the inline "add new category" flow
 * on the material form.
 */

require_once __DIR__ . '/../core/Database.php';

class Category
{
    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByPrefix(string $prefix): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE code_prefix = :prefix LIMIT 1');
        $stmt->execute(['prefix' => $prefix]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Creates a new category. Caller is responsible for validating
     * uniqueness of name/prefix beforehand (form-level validation);
     * the DB UNIQUE constraints are the final backstop.
     */
    public static function create(string $name, string $prefix): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO categories (name, code_prefix) VALUES (:name, :prefix)'
        );
        $stmt->execute([
            'name'   => $name,
            'prefix' => strtoupper($prefix),
        ]);
        return (int) $pdo->lastInsertId();
    }
}
