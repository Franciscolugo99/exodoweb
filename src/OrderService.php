<?php
declare(strict_types=1);

namespace Exodo;

use PDO;

final class OrderService
{
    /**
     * Valida el carrito contra la base de datos y devuelve un resumen
     * con posibles discrepancias (precio cambiado, producto agotado, etc.).
     */
    public static function validateCart(array $items, string $deliveryType): array
    {
        $pdo = Database::getInstance();
        $issues = [];
        $validatedItems = [];

        if ($items === []) {
            $issues[] = ['type' => 'empty_cart', 'message' => 'El carrito está vacío.'];
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $issues[] = ['index' => $index, 'type' => 'invalid_item', 'message' => 'Ítem de carrito inválido.'];
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $quantity  = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0) {
                $issues[] = ['index' => $index, 'type' => 'invalid_product', 'message' => 'Producto inválido.'];
                continue;
            }

            if ($quantity < 1 || $quantity > 99) {
                $issues[] = ['index' => $index, 'type' => 'invalid_quantity', 'message' => 'Cantidad inválida.'];
                continue;
            }

            $stmt = $pdo->prepare(
                'SELECT id, name, price, is_available FROM products WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                $issues[] = ['index' => $index, 'type' => 'not_found', 'message' => 'El producto ya no existe.'];
                continue;
            }

            if (!$product['is_available']) {
                $issues[] = ['index' => $index, 'type' => 'unavailable', 'message' => "El producto \"{$product['name']}\" no está disponible."];
                continue;
            }

            $currentPrice = (float) $product['price'];
            $sentPrice    = (float) ($item['unit_price'] ?? 0);

            if (abs($currentPrice - $sentPrice) > 0.01) {
                $issues[] = [
                    'index'      => $index,
                    'type'       => 'price_changed',
                    'message'    => "El precio de \"{$product['name']}\" cambió de \${$sentPrice} a \${$currentPrice}.",
                    'old_price'  => $sentPrice,
                    'new_price'  => $currentPrice,
                ];
            }

            // Validar ingredientes quitados
            $removedIngredients = $item['removed_ingredients'] ?? [];
            if (!is_array($removedIngredients)) {
                $removedIngredients = [];
                $issues[] = ['index' => $index, 'type' => 'invalid_ingredient', 'message' => 'Ingredientes inválidos.'];
            }
            if (!empty($removedIngredients)) {
                $ingStmt = $pdo->prepare(
                    'SELECT i.id FROM product_ingredients pi
                     JOIN ingredients i ON i.id = pi.ingredient_id
                     WHERE pi.product_id = ? AND i.active = 1'
                );
                $ingStmt->execute([$productId]);
                $validIds = array_column($ingStmt->fetchAll(), 'id');
                $validIds = array_map('intval', $validIds);
                $filteredIngredients = [];
                foreach ($removedIngredients as $ingId) {
                    $ingId = (int) $ingId;
                    if (!in_array($ingId, $validIds, true)) {
                        $issues[] = ['index' => $index, 'type' => 'invalid_ingredient', 'message' => 'Ingrediente inválido.'];
                        continue;
                    }
                    $filteredIngredients[] = $ingId;
                }
                $removedIngredients = array_values(array_unique($filteredIngredients));
            }

            $customRemovals = trim(preg_replace('/[\r\n]+/u', ' ', (string) ($item['custom_removals'] ?? '')) ?? '');
            $customRemovals = mb_substr($customRemovals, 0, 250);
            $customNotes = trim(preg_replace('/[\r\n]+/u', ' ', (string) ($item['custom_notes'] ?? '')) ?? '');
            $customNotes = mb_substr($customNotes, 0, 700);
            $kitchenInstructions = [];
            if ($customRemovals !== '') $kitchenInstructions[] = 'Quitar: ' . $customRemovals;
            if ($customNotes !== '') $kitchenInstructions[] = 'Indicaciones: ' . $customNotes;

            $validatedItems[] = [
                'product_id'          => $productId,
                'product_name'        => $product['name'],
                'unit_price'          => $currentPrice,
                'quantity'            => $quantity,
                'removed_ingredients' => $removedIngredients,
                'custom_notes'        => implode("\n", $kitchenInstructions),
            ];
        }

        // Calcular envío
        $deliveryFee = 0.0;
        if ($deliveryType === 'delivery') {
            $deliveryEnabled = Settings::get('delivery_enabled') === '1';
            if (!$deliveryEnabled) {
                $issues[] = ['type' => 'delivery_disabled', 'message' => 'El delivery no está disponible en este momento.'];
            } else {
                $deliveryFee = (float) Settings::get('delivery_fee', '0');
            }
        }

        $subtotal = array_sum(array_map(
            fn($i) => $i['unit_price'] * $i['quantity'],
            $validatedItems
        ));

        return [
            'items'        => $validatedItems,
            'issues'       => $issues,
            'subtotal'     => round($subtotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'total'        => round($subtotal + $deliveryFee, 2),
        ];
    }

    /**
     * Crea un pedido de forma idempotente.
     * Si el idempotency_key ya existe, devuelve el pedido existente.
     */
    public static function create(array $data, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey);
            if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
                throw new \RuntimeException('Clave de idempotencia inválida.');
            }
        }

        if ($idempotencyKey !== null) {
            $existing = self::findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                return $existing;
            }
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $validation = self::validateCart($data['items'], $data['delivery_type']);

            if (!empty($validation['issues'])) {
                $blocking = array_filter($validation['issues'], fn($i) =>
                in_array($i['type'], [
                    'empty_cart', 'invalid_item', 'invalid_product', 'invalid_quantity',
                    'unavailable', 'not_found', 'invalid_ingredient', 'delivery_disabled'
                ], true)
                );
                if (!empty($blocking)) {
                    $pdo->rollBack();
                    throw new \RuntimeException('No se puede crear el pedido: ' . $blocking[0]['message']);
                }
            }

            $trackingToken = bin2hex(random_bytes(32));
            $isDemo = Settings::get('demo_mode') === '1' ? 1 : 0;

            $stmt = $pdo->prepare(
                'INSERT INTO orders (
                    tracking_token, idempotency_key, status, delivery_type, payment_method, payment_status,
                    subtotal, delivery_fee, total,
                    customer_name, customer_phone,
                    delivery_street, delivery_number, delivery_locality,
                    delivery_apartment, delivery_floor, delivery_ring, delivery_notes,
                    notes, is_demo
                ) VALUES (
                    :token, :idempotency_key, :status, :delivery_type, :payment_method, :payment_status,
                    :subtotal, :delivery_fee, :total,
                    :customer_name, :customer_phone,
                    :delivery_street, :delivery_number, :delivery_locality,
                    :delivery_apartment, :delivery_floor, :delivery_ring, :delivery_notes,
                    :notes, :is_demo
                )'
            );

            $stmt->execute([
                ':token'            => $trackingToken,
                ':idempotency_key'  => $idempotencyKey,
                ':status'           => 'received',
                ':delivery_type'    => $data['delivery_type'],
                ':payment_method'   => $data['payment_method'] ?? 'cash',
                ':payment_status'   => 'pending',
                ':subtotal'         => $validation['subtotal'],
                ':delivery_fee'     => $validation['delivery_fee'],
                ':total'            => $validation['total'],
                ':customer_name'    => $data['customer_name'] ?? null,
                ':customer_phone'   => $data['customer_phone'] ?? null,
                ':delivery_street'  => $data['delivery_street'] ?? null,
                ':delivery_number'  => $data['delivery_number'] ?? null,
                ':delivery_locality'=> $data['delivery_locality'] ?? null,
                ':delivery_apartment'=> $data['delivery_apartment'] ?? null,
                ':delivery_floor'   => $data['delivery_floor'] ?? null,
                ':delivery_ring'    => $data['delivery_ring'] ?? null,
                ':delivery_notes'   => $data['delivery_notes'] ?? null,
                ':notes'            => $data['notes'] ?? null,
                ':is_demo'          => $isDemo,
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (
                    order_id, product_id, product_name, unit_price, quantity,
                    removed_ingredients, custom_notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($validation['items'] as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['unit_price'],
                    $item['quantity'],
                    json_encode($item['removed_ingredients']),
                    $item['custom_notes'],
                ]);
            }

            // Historial
            $histStmt = $pdo->prepare(
                'INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, NULL)'
            );
            $histStmt->execute([$orderId, 'received']);

            $pdo->commit();

            return [
                'order_id'       => $orderId,
                'tracking_token' => $trackingToken,
                'total'          => $validation['total'],
                'subtotal'       => $validation['subtotal'],
                'delivery_fee'   => $validation['delivery_fee'],
                'items'          => $validation['items'],
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('Order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private static function findByIdempotencyKey(string $key): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, tracking_token, total, subtotal, delivery_fee FROM orders WHERE idempotency_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        return [
            'order_id' => (int) $order['id'],
            'tracking_token' => $order['tracking_token'],
            'total' => (float) $order['total'],
            'subtotal' => (float) $order['subtotal'],
            'delivery_fee' => (float) $order['delivery_fee'],
        ];
    }

    public static function getByTrackingToken(string $token): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT o.*, 
                    GROUP_CONCAT(oi.product_name SEPARATOR "|") AS item_names
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.tracking_token = ?
             GROUP BY o.id
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $itemStmt = $pdo->prepare(
            'SELECT oi.*, p.name AS current_product_name
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?'
        );
        $itemStmt->execute([$order['id']]);
        $order['items'] = $itemStmt->fetchAll();

        return $order;
    }
}

