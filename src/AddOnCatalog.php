<?php
declare(strict_types=1);

namespace Exodo;

use PDO;

final class AddOnCatalog
{
    public static function definitions(): array
    {
        return [
            'meat' => [
                'name' => 'Medallón de carne',
                'setting_key' => 'addon_meat_enabled',
                'default_available' => false,
            ],
            'bacon' => [
                'name' => 'Bacon',
                'setting_key' => 'addon_bacon_enabled',
                'default_available' => true,
            ],
            'cheddar' => [
                'name' => 'Cheddar',
                'setting_key' => 'addon_cheddar_enabled',
                'default_available' => true,
            ],
        ];
    }

    public static function all(PDO $pdo, ?array $settings = null): array
    {
        $definitions = self::definitions();
        $names = array_column($definitions, 'name');
        $marks = implode(',', array_fill(0, count($names), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price, active FROM ingredients WHERE name IN ($marks)");
        $stmt->execute($names);

        $ingredients = [];
        foreach ($stmt->fetchAll() as $ingredient) {
            $ingredients[mb_strtolower((string) $ingredient['name'])] = $ingredient;
        }

        $settings ??= Settings::all();
        $result = [];
        foreach ($definitions as $key => $definition) {
            $ingredient = $ingredients[mb_strtolower($definition['name'])] ?? null;
            $enabledBySetting = ($settings[$definition['setting_key']] ?? ($definition['default_available'] ? '1' : '0')) === '1';
            $result[] = [
                'key' => $key,
                'name' => $definition['name'],
                'ingredient_id' => $ingredient ? (int) $ingredient['id'] : null,
                'price' => $ingredient ? (float) $ingredient['price'] : 0.0,
                'available' => $enabledBySetting && $ingredient !== null && (bool) $ingredient['active'],
            ];
        }

        return $result;
    }

    public static function available(PDO $pdo, ?array $settings = null): array
    {
        return array_values(array_map(
            static fn(array $addOn): array => [
                'ingredient_id' => $addOn['ingredient_id'],
                'name' => $addOn['name'],
                'price' => $addOn['price'],
            ],
            array_filter(self::all($pdo, $settings), static fn(array $addOn): bool => $addOn['available'])
        ));
    }
}
