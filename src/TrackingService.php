<?php
declare(strict_types=1);

namespace Exodo;

final class TrackingService
{
    private const STATUSES = [
        'received', 'preparing', 'ready_for_pickup', 'ready_for_delivery',
        'on_the_way', 'delivered', 'cancelled',
    ];

    public static function transition(int $orderId, string $status, int $userId): bool
    {
        if ($orderId < 1 || !in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Estado inválido.');
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            throw new \RuntimeException('Pedido no encontrado.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $orderId]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, ?)')->execute([$orderId, $status, $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return true;
    }
}

