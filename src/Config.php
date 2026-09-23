<?php
declare(strict_types=1);

namespace Exodo;

final class Config
{
    private static ?array $data = null;

    public static function isConfigured(): bool
    {
        return is_file(self::path());
    }

    public static function all(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        if (!self::isConfigured()) {
            throw new \RuntimeException('ÉXODO todavía no está instalado.');
        }

        $data = require self::path();
        if (!is_array($data) || !isset($data['db']) || !is_array($data['db'])) {
            throw new \RuntimeException('La configuración de ÉXODO es inválida.');
        }

        self::$data = $data;
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all();
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private static function path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';
    }
}

