<?php
declare(strict_types=1);

namespace Exodo;

final class Settings
{
    public static function all(): array
    {
        $rows = Database::getInstance()->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        return $settings;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::getInstance()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
    }
}

