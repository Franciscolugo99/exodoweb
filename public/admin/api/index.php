<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../src/bootstrap.php';

use Exodo\Auth;
use Exodo\Csrf;
use Exodo\Database;
use Exodo\Settings;
use Exodo\TrackingService;

header('Content-Type: application/json; charset=utf-8');

$user = Auth::user();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'No autenticado.']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403); echo json_encode(['error' => 'CSRF inválido.']); exit;
    }
}

$action = $_GET['action'] ?? '';
$pdo = Database::getInstance();
$role = $user['role'];

try {
    switch ($action) {
        case 'orders.list':
            $status = $_GET['status'] ?? '';
            $delivery = $_GET['delivery_type'] ?? '';
            $sql = 'SELECT * FROM orders WHERE 1=1';
            $params = [];
            if ($status)   { $sql .= ' AND status = ?'; $params[] = $status; }
            if ($delivery) { $sql .= ' AND delivery_type = ?'; $params[] = $delivery; }
            $sql .= ' ORDER BY id DESC LIMIT 100';
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $orders = $stmt->fetchAll();
            if ($orders) {
                $orderIds = array_map('intval', array_column($orders, 'id'));
                $orderMarks = implode(',', array_fill(0, count($orderIds), '?'));
                $itemsStmt = $pdo->prepare("SELECT order_id, product_name, quantity, removed_ingredients, custom_notes FROM order_items WHERE order_id IN ($orderMarks) ORDER BY id");
                $itemsStmt->execute($orderIds);
                $items = $itemsStmt->fetchAll();
                $removedIds = [];
                foreach ($items as $item) {
                    $ids = json_decode((string) ($item['removed_ingredients'] ?? '[]'), true);
                    if (is_array($ids)) {
                        foreach ($ids as $ingredientId) {
                            $ingredientId = (int) $ingredientId;
                            if ($ingredientId > 0) $removedIds[] = $ingredientId;
                        }
                    }
                }
                $ingredientMap = [];
                $removedIds = array_values(array_unique($removedIds));
                if ($removedIds) {
                    $ingredientMarks = implode(',', array_fill(0, count($removedIds), '?'));
                    $ingredientStmt = $pdo->prepare("SELECT id, name FROM ingredients WHERE id IN ($ingredientMarks)");
                    $ingredientStmt->execute($removedIds);
                    foreach ($ingredientStmt->fetchAll() as $ingredient) {
                        $ingredientMap[(int) $ingredient['id']] = $ingredient['name'];
                    }
                }
                $itemsByOrder = [];
                foreach ($items as $item) {
                    $ids = json_decode((string) ($item['removed_ingredients'] ?? '[]'), true);
                    $names = [];
                    if (is_array($ids)) {
                        foreach ($ids as $ingredientId) {
                            $ingredientId = (int) $ingredientId;
                            if (isset($ingredientMap[$ingredientId])) $names[] = $ingredientMap[$ingredientId];
                        }
                    }
                    $itemsByOrder[(int) $item['order_id']][] = [
                        'product_name' => $item['product_name'],
                        'quantity' => (int) $item['quantity'],
                        'removed_ingredient_names' => array_values(array_unique($names)),
                        'custom_notes' => $item['custom_notes'],
                    ];
                }
                foreach ($orders as &$order) $order['items'] = $itemsByOrder[(int) $order['id']] ?? [];
                unset($order);
            }
            echo json_encode(['orders' => $orders]);
            break;

        case 'orders.get':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1'); $stmt->execute([$id]);
            $order = $stmt->fetch();
            if (!$order) { http_response_code(404); echo json_encode(['error' => 'No encontrado.']); exit; }
            $is = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?'); $is->execute([$id]);
            $order['items'] = $is->fetchAll();
            $ingredientNames = $pdo->prepare('SELECT name FROM ingredients WHERE id = ? LIMIT 1');
            foreach ($order['items'] as &$item) {
                $removedIds = json_decode((string) ($item['removed_ingredients'] ?? '[]'), true);
                $item['removed_ingredient_names'] = [];
                if (is_array($removedIds)) {
                    foreach ($removedIds as $ingredientId) {
                        $ingredientNames->execute([(int) $ingredientId]);
                        $ingredientName = $ingredientNames->fetchColumn();
                        if ($ingredientName !== false) $item['removed_ingredient_names'][] = $ingredientName;
                    }
                }
            }
            unset($item);
            echo json_encode(['order' => $order]);
            break;

        case 'orders.update-status':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $in = json_decode(file_get_contents('php://input'), true);
            $ok = TrackingService::transition((int) $in['id'], (string) $in['status'], (int) $user['id']);
            echo json_encode(['ok' => $ok]);
            break;

        case 'products.list':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $rows = $pdo->query('SELECT id, name, description, price, image_url, is_available FROM products ORDER BY sort_order, name')->fetchAll();
            $ingredientQuery = $pdo->prepare('SELECT i.name FROM product_ingredients pi JOIN ingredients i ON i.id = pi.ingredient_id WHERE pi.product_id = ? AND i.active = 1 ORDER BY i.sort_order, i.name');
            foreach ($rows as &$product) {
                $ingredientQuery->execute([(int) $product['id']]);
                $product['ingredients'] = array_column($ingredientQuery->fetchAll(), 'name');
            }
            unset($product);
            echo json_encode(['products' => $rows]);
            break;

        case 'products.update':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $in = json_decode(file_get_contents('php://input'), true);
            if (!is_array($in)) { http_response_code(400); echo json_encode(['error' => 'Datos inválidos.']); exit; }
            $id = (int) ($in['id'] ?? 0);
            $name = trim((string) ($in['name'] ?? ''));
            $description = trim((string) ($in['description'] ?? ''));
            $price = $in['price'] ?? null;
            $imageUrl = trim((string) ($in['image_url'] ?? ''));
            $ingredientInput = trim((string) ($in['ingredients'] ?? ''));
            $ingredientNames = $ingredientInput === '' ? [] : array_values(array_unique(array_filter(array_map('trim', explode(',', $ingredientInput)))));
            $available = (int) ($in['is_available'] ?? -1);
            $localImage = preg_match('#^/assets/img/[A-Za-z0-9._-]+$#', $imageUrl) === 1;
            $remoteImage = filter_var($imageUrl, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
            if ($id < 1 || $name === '' || mb_strlen($name) > 150 || mb_strlen($description) > 2000
                || !is_numeric($price) || (float) $price <= 0 || (float) $price > 100000000
                || mb_strlen($imageUrl) > 500 || ($imageUrl !== '' && !$localImage && !$remoteImage)
                || mb_strlen($ingredientInput) > 2000 || count($ingredientNames) > 30
                || array_filter($ingredientNames, static fn($ingredient) => $ingredient === '' || mb_strlen($ingredient) > 100)
                || !in_array($available, [0, 1], true)) {
                http_response_code(400); echo json_encode(['error' => 'Revisá nombre, descripción, precio, imagen, ingredientes y disponibilidad.']); exit;
            }
            $check = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
            $check->execute([$id]);
            if (!$check->fetchColumn()) { http_response_code(404); echo json_encode(['error' => 'Producto no encontrado.']); exit; }
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, is_available = ? WHERE id = ?');
                $stmt->execute([$name, $description, (float) $price, $imageUrl !== '' ? $imageUrl : null, $available, $id]);
                $deleteIngredients = $pdo->prepare('DELETE FROM product_ingredients WHERE product_id = ?');
                $deleteIngredients->execute([$id]);
                $findIngredient = $pdo->prepare('SELECT id FROM ingredients WHERE name = ? LIMIT 1');
                $createIngredient = $pdo->prepare('INSERT INTO ingredients (name, price, active, sort_order) VALUES (?, 0, 1, 1000)');
                $linkIngredient = $pdo->prepare('INSERT INTO product_ingredients (product_id, ingredient_id, is_default) VALUES (?, ?, 1)');
                foreach ($ingredientNames as $ingredientName) {
                    $findIngredient->execute([$ingredientName]);
                    $ingredientId = $findIngredient->fetchColumn();
                    if (!$ingredientId) {
                        $createIngredient->execute([$ingredientName]);
                        $ingredientId = $pdo->lastInsertId();
                    } else {
                        $reactivateIngredient = $pdo->prepare('UPDATE ingredients SET active = 1 WHERE id = ?');
                        $reactivateIngredient->execute([(int) $ingredientId]);
                    }
                    $linkIngredient->execute([$id, (int) $ingredientId]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            echo json_encode(['ok' => true]);
            break;

        case 'settings.get':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            echo json_encode(['settings' => Settings::all()]);
            break;

        case 'settings.save':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $in = json_decode(file_get_contents('php://input'), true);
            foreach ($in as $k => $v) {
                if (preg_match('/^[a-z0-9_]+$/', $k)) Settings::set($k, (string) $v);
            }
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Acción desconocida.']);
    }
} catch (Throwable $e) {
    error_log('Admin API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}

