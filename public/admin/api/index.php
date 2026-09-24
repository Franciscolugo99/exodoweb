<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../src/bootstrap.php';

use Exodo\AddOnCatalog;
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

function storeProductImage(array $upload, int $productId): string
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException('La foto supera el límite de carga del servidor. Elegí una imagen más liviana.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No se pudo leer la foto. Volvé a elegir el archivo e intentá otra vez.');
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('La foto debe pesar menos de 5 MB. Elegí otra imagen.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_array($imageInfo) || !isset($extensions[$mime]) || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Formato no válido. Subí una imagen JPG, PNG o WebP.');
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 25000000) {
        throw new InvalidArgumentException('La foto tiene una resolución demasiado grande. Reducí su tamaño e intentá otra vez.');
    }

    $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($uploadDirectory) && !@mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('No se pudo preparar la carpeta de fotos de productos.');
    }
    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('La carpeta de fotos de productos no tiene permisos de escritura.');
    }

    $filename = 'product-' . $productId . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('No se pudo guardar la foto del producto.');
    }

    return '/uploads/products/' . $filename;
}

try {
    switch ($action) {
        case 'analytics.get':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }

            $range = (string) ($_GET['range'] ?? '30d');
            $delivery = (string) ($_GET['delivery_type'] ?? '');
            if (!in_array($range, ['7d', '30d', 'month', 'all', 'custom'], true)) {
                throw new InvalidArgumentException('El período seleccionado no es válido.');
            }
            if (!in_array($delivery, ['', 'pickup', 'delivery'], true)) {
                throw new InvalidArgumentException('La modalidad seleccionada no es válida.');
            }

            $parseDate = static function (string $value): ?DateTimeImmutable {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                $errors = DateTimeImmutable::getLastErrors();
                if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
                    return null;
                }
                return $date;
            };
            $clientToday = isset($_GET['today']) ? $parseDate((string) $_GET['today']) : null;
            if (isset($_GET['today']) && !$clientToday) throw new InvalidArgumentException('La fecha actual del filtro no es válida.');
            $today = $clientToday ?: new DateTimeImmutable('today');

            if ($range === '7d') {
                $from = $today->modify('-6 days');
                $to = $today;
            } elseif ($range === '30d') {
                $from = $today->modify('-29 days');
                $to = $today;
            } elseif ($range === 'month') {
                $from = $today->modify('first day of this month');
                $to = $today;
            } elseif ($range === 'custom') {
                $from = $parseDate((string) ($_GET['from'] ?? ''));
                $to = $parseDate((string) ($_GET['to'] ?? ''));
                if (!$from || !$to) throw new InvalidArgumentException('Elegí una fecha de inicio y una fecha de fin válidas.');
                if ($from > $to) throw new InvalidArgumentException('La fecha de inicio no puede ser posterior a la fecha de fin.');
                if ($to > $today) throw new InvalidArgumentException('La fecha de fin no puede ser futura.');
            } else {
                $earliestSql = "SELECT MIN(DATE(created_at)) FROM orders WHERE status <> 'cancelled' AND payment_status NOT IN ('failed', 'refunded')";
                $earliestParams = [];
                if ($delivery !== '') {
                    $earliestSql .= ' AND delivery_type = ?';
                    $earliestParams[] = $delivery;
                }
                $earliestQuery = $pdo->prepare($earliestSql);
                $earliestQuery->execute($earliestParams);
                $earliest = $earliestQuery->fetchColumn();
                $from = $earliest ? new DateTimeImmutable((string) $earliest) : $today;
                $to = $today;
            }

            $fromValue = $from->format('Y-m-d');
            $toValue = $to->format('Y-m-d');
            $baseWhere = "o.status <> 'cancelled' AND o.payment_status NOT IN ('failed', 'refunded') AND o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
            $baseParams = [$fromValue, $toValue];
            if ($delivery !== '') {
                $baseWhere .= ' AND o.delivery_type = ?';
                $baseParams[] = $delivery;
            }

            $summaryQuery = $pdo->prepare("SELECT COALESCE(SUM(o.total), 0) AS total, COUNT(*) AS order_count, COALESCE(AVG(o.total), 0) AS average_ticket FROM orders o WHERE $baseWhere");
            $summaryQuery->execute($baseParams);
            $summary = $summaryQuery->fetch();

            $days = (int) $from->diff($to)->days + 1;
            $bucket = $days <= 90 ? 'day' : 'month';
            $periodExpression = $bucket === 'day' ? "DATE_FORMAT(o.created_at, '%Y-%m-%d')" : "DATE_FORMAT(o.created_at, '%Y-%m-01')";
            $timelineQuery = $pdo->prepare("SELECT $periodExpression AS period, COALESCE(SUM(o.total), 0) AS total, COUNT(*) AS order_count FROM orders o WHERE $baseWhere GROUP BY period ORDER BY period");
            $timelineQuery->execute($baseParams);

            $productsQuery = $pdo->prepare("SELECT oi.product_name, COALESCE(SUM(oi.quantity), 0) AS units, COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $baseWhere GROUP BY oi.product_name ORDER BY units DESC, revenue DESC, oi.product_name ASC");
            $productsQuery->execute($baseParams);
            $products = array_map(static fn(array $product): array => [
                'product_name' => (string) $product['product_name'],
                'units' => (int) $product['units'],
                'revenue' => (float) $product['revenue'],
            ], $productsQuery->fetchAll());

            echo json_encode([
                'summary' => [
                    'total' => (float) ($summary['total'] ?? 0),
                    'order_count' => (int) ($summary['order_count'] ?? 0),
                    'average_ticket' => (float) ($summary['average_ticket'] ?? 0),
                    'top_product' => $products[0] ?? null,
                ],
                'timeline' => $timelineQuery->fetchAll(),
                'products' => $products,
                'bucket' => $bucket,
                'filters' => ['from' => $fromValue, 'to' => $toValue, 'range' => $range, 'delivery_type' => $delivery],
            ]);
            break;

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
            $sales = $pdo->query("SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS order_count FROM orders WHERE status <> 'cancelled' AND payment_status NOT IN ('failed', 'refunded')")->fetch();
            if ($orders) {
                $orderIds = array_map('intval', array_column($orders, 'id'));
                $orderMarks = implode(',', array_fill(0, count($orderIds), '?'));
                $itemsStmt = $pdo->prepare("SELECT order_id, product_name, quantity, removed_ingredients, added_ingredients, custom_notes FROM order_items WHERE order_id IN ($orderMarks) ORDER BY id");
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
                    $added = json_decode((string) ($item['added_ingredients'] ?? '[]'), true);
                    if (!is_array($added)) $added = [];
                    $added = array_values(array_filter(array_map(static function ($extra): ?array {
                        if (!is_array($extra) || trim((string) ($extra['name'] ?? '')) === '') return null;
                        return [
                            'name' => (string) $extra['name'],
                            'quantity' => max(1, (int) ($extra['quantity'] ?? 1)),
                            'unit_price' => max(0, (float) ($extra['unit_price'] ?? 0)),
                        ];
                    }, $added)));
                    $itemsByOrder[(int) $item['order_id']][] = [
                        'product_name' => $item['product_name'],
                        'quantity' => (int) $item['quantity'],
                        'removed_ingredient_names' => array_values(array_unique($names)),
                        'added_ingredients' => $added,
                        'custom_notes' => $item['custom_notes'],
                        'is_customized' => !empty($names) || !empty($added) || trim((string) ($item['custom_notes'] ?? '')) !== '',
                    ];
                }
                foreach ($orders as &$order) $order['items'] = $itemsByOrder[(int) $order['id']] ?? [];
                unset($order);
            }
            echo json_encode(['orders' => $orders, 'sales_summary' => [
                'total' => (float) ($sales['total'] ?? 0),
                'order_count' => (int) ($sales['order_count'] ?? 0),
            ]]);
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
                $added = json_decode((string) ($item['added_ingredients'] ?? '[]'), true);
                if (!is_array($added)) $added = [];
                $item['added_ingredients'] = array_values(array_filter(array_map(static function ($extra): ?array {
                    if (!is_array($extra) || trim((string) ($extra['name'] ?? '')) === '') return null;
                    return [
                        'name' => (string) $extra['name'],
                        'quantity' => max(1, (int) ($extra['quantity'] ?? 1)),
                        'unit_price' => max(0, (float) ($extra['unit_price'] ?? 0)),
                    ];
                }, $added)));
                $removedIds = json_decode((string) ($item['removed_ingredients'] ?? '[]'), true);
                $item['removed_ingredient_names'] = [];
                if (is_array($removedIds)) {
                    foreach ($removedIds as $ingredientId) {
                        $ingredientNames->execute([(int) $ingredientId]);
                        $ingredientName = $ingredientNames->fetchColumn();
                        if ($ingredientName !== false) $item['removed_ingredient_names'][] = $ingredientName;
                    }
                }
                $item['is_customized'] = !empty($item['removed_ingredient_names']) || !empty($item['added_ingredients']) || trim((string) ($item['custom_notes'] ?? '')) !== '';
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

        case 'addons.list':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            echo json_encode(['add_ons' => AddOnCatalog::all($pdo)]);
            break;

        case 'addons.save':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $in = json_decode(file_get_contents('php://input'), true);
            if (!is_array($in)) throw new InvalidArgumentException('Los datos de los extras no son válidos.');
            $entries = $in['add_ons'] ?? null;
            $definitions = AddOnCatalog::definitions();
            if (!is_array($entries) || count($entries) !== count($definitions)) {
                throw new InvalidArgumentException('Revisá los precios y la disponibilidad de los tres extras.');
            }
            $validated = [];
            foreach ($entries as $entry) {
                $entryKey = is_array($entry) ? ($entry['key'] ?? null) : null;
                if (!is_string($entryKey) || !isset($definitions[$entryKey])) {
                    throw new InvalidArgumentException('Hay un extra desconocido. Actualizá el panel e intentá de nuevo.');
                }
                $key = $entryKey;
                $price = $entry['price'] ?? null;
                $available = $entry['available'] ?? null;
                if (isset($validated[$key]) || !is_numeric($price)
                    || preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $price) !== 1
                    || !in_array($available, [true, false, 0, 1, '0', '1'], true)) {
                    throw new InvalidArgumentException('Usá precios entre $ 0 y $ 99.999.999, con hasta dos decimales.');
                }
                $validated[$key] = [
                    'name' => $definitions[$key]['name'],
                    'price' => (float) $price,
                    'available' => in_array($available, [true, 1, '1'], true),
                ];
            }
            if (count($validated) !== count($definitions)) {
                throw new InvalidArgumentException('Falta configurar alguno de los extras.');
            }

            $pdo->beginTransaction();
            try {
                $findIngredient = $pdo->prepare('SELECT id FROM ingredients WHERE name = ? LIMIT 1');
                $createIngredient = $pdo->prepare('INSERT INTO ingredients (name, price, active, sort_order) VALUES (?, ?, 1, 1000)');
                $updateIngredient = $pdo->prepare('UPDATE ingredients SET price = ?, active = 1 WHERE name = ?');
                foreach ($validated as $key => $addOn) {
                    $findIngredient->execute([$addOn['name']]);
                    if ($findIngredient->fetchColumn()) {
                        $updateIngredient->execute([$addOn['price'], $addOn['name']]);
                    } else {
                        $createIngredient->execute([$addOn['name'], $addOn['price']]);
                    }
                    Settings::set($definitions[$key]['setting_key'], $addOn['available'] ? '1' : '0');
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            echo json_encode(['ok' => true, 'add_ons' => AddOnCatalog::all($pdo)]);
            break;

        case 'products.list':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $rows = $pdo->query('SELECT id, name, description, price, image_url, is_available FROM products ORDER BY sort_order, name')->fetchAll();
            $ingredientQuery = $pdo->prepare('SELECT i.name FROM product_ingredients pi JOIN ingredients i ON i.id = pi.ingredient_id WHERE pi.product_id = ? AND pi.is_default = 1 AND i.active = 1 ORDER BY i.sort_order, i.name');
            foreach ($rows as &$product) {
                $ingredientQuery->execute([(int) $product['id']]);
                $product['ingredients'] = array_column($ingredientQuery->fetchAll(), 'name');
            }
            unset($product);
            echo json_encode(['products' => $rows]);
            break;

        case 'products.update':
            if ($role !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Sin permisos.']); exit; }
            $uploadError = (int) ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                http_response_code(400); echo json_encode(['error' => 'La foto supera el límite de carga del servidor. Elegí una imagen más liviana.']); exit;
            }
            $in = $_POST;
            if ($in === []) $in = json_decode(file_get_contents('php://input'), true);
            if (!is_array($in)) { http_response_code(400); echo json_encode(['error' => 'Datos inválidos.']); exit; }
            $id = (int) ($in['id'] ?? 0);
            $name = trim((string) ($in['name'] ?? ''));
            $description = trim((string) ($in['description'] ?? ''));
            $price = $in['price'] ?? null;
            $imageUrl = trim((string) ($in['image_url'] ?? ''));
            $ingredientInput = trim((string) ($in['ingredients'] ?? ''));
            $ingredientNames = $ingredientInput === '' ? [] : array_values(array_unique(array_filter(array_map('trim', explode(',', $ingredientInput)))));
            $available = (int) ($in['is_available'] ?? -1);
            $localImage = preg_match('#^/assets/img/[A-Za-z0-9._-]+$#', $imageUrl) === 1
                || preg_match('#^/uploads/products/product-[1-9][0-9]*-[a-f0-9]{16}\.(jpg|png|webp)$#', $imageUrl) === 1;
            $remoteImage = filter_var($imageUrl, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
            if ($id < 1 || $name === '' || mb_strlen($name) > 150 || mb_strlen($description) > 2000
                || !is_numeric($price) || (float) $price <= 0 || (float) $price > 100000000
                || mb_strlen($imageUrl) > 500 || ($imageUrl !== '' && !$localImage && !$remoteImage)
                || mb_strlen($ingredientInput) > 2000 || count($ingredientNames) > 30
                || array_filter($ingredientNames, static fn($ingredient) => $ingredient === '' || mb_strlen($ingredient) > 100)
                || !in_array($available, [0, 1], true)) {
                http_response_code(400); echo json_encode(['error' => 'Revisá nombre, descripción, precio, imagen e ingredientes.']); exit;
            }
            $check = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
            $check->execute([$id]);
            if (!$check->fetchColumn()) { http_response_code(404); echo json_encode(['error' => 'Producto no encontrado.']); exit; }
            if (isset($_FILES['image_file']) && (int) ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imageUrl = storeProductImage($_FILES['image_file'], $id);
            }
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, is_available = ? WHERE id = ?');
                $stmt->execute([$name, $description, (float) $price, $imageUrl !== '' ? $imageUrl : null, $available, $id]);
                $clearDefaults = $pdo->prepare('UPDATE product_ingredients SET is_default = 0 WHERE product_id = ? AND is_default = 1');
                $clearDefaults->execute([$id]);
                $findIngredient = $pdo->prepare('SELECT id FROM ingredients WHERE name = ? LIMIT 1');
                $createIngredient = $pdo->prepare('INSERT INTO ingredients (name, price, active, sort_order) VALUES (?, 0, 1, 1000)');
                $linkIngredient = $pdo->prepare('INSERT INTO product_ingredients (product_id, ingredient_id, is_default, is_addable) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_default = VALUES(is_default), is_addable = VALUES(is_addable)');
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
                    $linkIngredient->execute([$id, (int) $ingredientId, 1, 0]);
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
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Admin API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}

