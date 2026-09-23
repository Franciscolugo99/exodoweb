<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

use Exodo\Config;
use Exodo\Database;
use Exodo\OrderService;
use Exodo\Settings;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Config::isConfigured()) {
    respond(['error' => 'La aplicación todavía no está instalada. Abrí /install/.'], 503);
}

$action = (string) ($_GET['action'] ?? '');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $pdo = Database::getInstance();

    if ($action === 'catalog' && $method === 'GET') {
        $categories = $pdo->query('SELECT id, name, slug FROM categories WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
        $products = $pdo->query(
            'SELECT id, category_id, name, description, price, image_url, is_available, is_demo, is_promo
             FROM products WHERE is_available = 1 ORDER BY sort_order, name'
        )->fetchAll();
        $ingredients = $pdo->query(
            'SELECT pi.product_id, i.id AS ingredient_id, i.name
             FROM product_ingredients pi JOIN ingredients i ON i.id = pi.ingredient_id
             WHERE i.active = 1 ORDER BY i.sort_order, i.name'
        )->fetchAll();

        $byProduct = [];
        foreach ($ingredients as $ingredient) {
            $byProduct[(int) $ingredient['product_id']][] = [
                'ingredient_id' => (int) $ingredient['ingredient_id'],
                'name' => (string) $ingredient['name'],
            ];
        }
        foreach ($products as &$product) {
            $product['id'] = (int) $product['id'];
            $product['category_id'] = (int) $product['category_id'];
            $product['price'] = (float) $product['price'];
            $product['is_demo'] = (bool) $product['is_demo'];
            $product['is_promo'] = (bool) $product['is_promo'];
            $product['ingredients'] = $byProduct[$product['id']] ?? [];
        }
        unset($product);

        $settings = Settings::all();
        $promo = null;
        foreach ($products as $product) {
            if ($product['is_promo']) { $promo = $product; break; }
        }
        respond([
            'categories' => $categories,
            'products' => $products,
            'promo' => $promo,
            'orders_open' => ($settings['orders_open'] ?? '1') === '1',
            'demo_mode' => ($settings['demo_mode'] ?? '1') === '1',
            'delivery_enabled' => ($settings['delivery_enabled'] ?? '0') === '1',
            'delivery_fee' => (float) ($settings['delivery_fee'] ?? '0'),
        ]);
    }

    if ($action === 'track' && $method === 'GET') {
        $token = (string) ($_GET['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) respond(['error' => 'Token inválido.'], 400);
        $order = OrderService::getByTrackingToken($token);
        if (!$order) respond(['error' => 'Pedido no encontrado.'], 404);
        respond($order);
    }

    if ($action === 'create-order' && $method === 'POST') {
        $settings = Settings::all();
        if (($settings['orders_open'] ?? '1') !== '1') respond(['error' => 'El local está cerrado y no recibe pedidos.'], 409);
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) respond(['error' => 'Datos inválidos.'], 400);

        $deliveryType = (string) ($input['delivery_type'] ?? '');
        if (!in_array($deliveryType, ['pickup', 'delivery'], true)) respond(['error' => 'Modalidad de entrega inválida.'], 400);
        if ($deliveryType === 'delivery' && ($settings['delivery_enabled'] ?? '0') !== '1') {
            respond(['error' => 'El delivery no está disponible en este momento.'], 409);
        }
        $paymentMethod = (string) ($input['payment_method'] ?? 'cash');
        if (!in_array($paymentMethod, ['cash', 'mercadopago'], true)) respond(['error' => 'Medio de pago inválido.'], 400);
        if ($paymentMethod === 'mercadopago' && ($settings['mercadopago_enabled'] ?? '0') !== '1') {
            respond(['error' => 'Mercado Pago todavía no está habilitado.'], 409);
        }

        $result = OrderService::create([
            'items' => is_array($input['items'] ?? null) ? $input['items'] : [],
            'delivery_type' => $deliveryType,
            'payment_method' => $paymentMethod,
            'customer_name' => trim((string) ($input['customer_name'] ?? '')) ?: null,
            'customer_phone' => trim((string) ($input['customer_phone'] ?? '')) ?: null,
            'delivery_street' => trim((string) ($input['delivery_street'] ?? '')) ?: null,
            'delivery_number' => trim((string) ($input['delivery_number'] ?? '')) ?: null,
            'delivery_locality' => trim((string) ($input['delivery_locality'] ?? '')) ?: null,
            'delivery_apartment' => trim((string) ($input['delivery_apartment'] ?? '')) ?: null,
            'delivery_floor' => trim((string) ($input['delivery_floor'] ?? '')) ?: null,
            'delivery_ring' => trim((string) ($input['delivery_ring'] ?? '')) ?: null,
            'delivery_notes' => trim((string) ($input['delivery_notes'] ?? '')) ?: null,
        ], isset($input['idempotency_key']) ? (string) $input['idempotency_key'] : null);
        respond($result, 201);
    }

    respond(['error' => 'Acción desconocida.'], 404);
} catch (Throwable $e) {
    error_log('Public API error: ' . $e->getMessage());
    respond(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'Error interno.'], 500);
}

