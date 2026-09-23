-- ÉXODO — Esquema de base de datos
-- Versión: 1.0.0
-- Requiere: MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- CONFIGURACIÓN
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USUARIOS Y PERMISOS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner','kitchen') NOT NULL DEFAULT 'kitchen',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CATEGORÍAS
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INGREDIENTES (para personalización)
-- ============================================================
CREATE TABLE IF NOT EXISTS ingredients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    is_demo TINYINT(1) NOT NULL DEFAULT 1,
    is_promo TINYINT(1) NOT NULL DEFAULT 0,
    promo_product_ids VARCHAR(255) DEFAULT NULL COMMENT 'IDs separados por coma para promos de 2',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INGREDIENTES POR PRODUCTO (qué se puede quitar)
-- ============================================================
CREATE TABLE IF NOT EXISTS product_ingredients (
    product_id INT UNSIGNED NOT NULL,
    ingredient_id INT UNSIGNED NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (product_id, ingredient_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PEDIDOS
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_token CHAR(64) NOT NULL UNIQUE,
    idempotency_key VARCHAR(100) DEFAULT NULL UNIQUE,
    status ENUM(
        'received',
        'preparing',
        'ready_for_pickup',
        'ready_for_delivery',
        'on_the_way',
        'delivered',
        'cancelled'
    ) NOT NULL DEFAULT 'received',
    delivery_type ENUM('pickup','delivery') NOT NULL,
    payment_method ENUM('cash','mercadopago') NOT NULL DEFAULT 'cash',
    payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    customer_name VARCHAR(150) DEFAULT NULL,
    customer_phone VARCHAR(50) DEFAULT NULL,
    delivery_street VARCHAR(200) DEFAULT NULL,
    delivery_number VARCHAR(20) DEFAULT NULL,
    delivery_locality VARCHAR(100) DEFAULT NULL,
    delivery_apartment VARCHAR(50) DEFAULT NULL,
    delivery_floor VARCHAR(20) DEFAULT NULL,
    delivery_ring VARCHAR(50) DEFAULT NULL,
    delivery_notes TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_demo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_tracking (tracking_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ITEMS DEL PEDIDO
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(150) NOT NULL COMMENT 'Nombre al momento de la compra',
    unit_price DECIMAL(10,2) NOT NULL COMMENT 'Precio al momento de la compra',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    removed_ingredients TEXT DEFAULT NULL COMMENT 'JSON array de IDs quitados',
    custom_notes TEXT DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- HISTORIAL DE ESTADOS
-- ============================================================
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,
    changed_by INT UNSIGNED DEFAULT NULL COMMENT 'user_id o NULL si fue automático',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAGOS (Mercado Pago)
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    external_id VARCHAR(100) DEFAULT NULL COMMENT 'ID de pago en Mercado Pago',
    preference_id VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    raw_response TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY uk_external_id (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RATE LIMITING
-- ============================================================
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS INICIALES DE DEMOSTRACIÓN
-- ============================================================
INSERT INTO settings (setting_key, setting_value) VALUES
('restaurant_name', 'ÉXODO'),
('restaurant_address', 'Dirección a configurar'),
('restaurant_whatsapp', ''),
('restaurant_hours', 'Horarios a configurar'),
('delivery_enabled', '0'),
('delivery_fee', '0.00'),
('orders_open', '1'),
('demo_mode', '1'),
('mercadopago_enabled', '0'),
('mercadopago_access_token', ''),
('mercadopago_public_key', ''),
('mercadopago_sandbox', '1'),
('currency', 'ARS');

INSERT INTO categories (name, slug, sort_order) VALUES
('Hamburguesas', 'hamburguesas', 1),
('Promociones', 'promociones', 2);

INSERT INTO ingredients (name, price, sort_order) VALUES
('Cheddar', 0.00, 1),
('Pepinillos', 0.00, 2),
('Cebolla', 0.00, 3),
('Bacon', 1500.00, 4),
('Huevo', 1200.00, 5),
('Salsa ÉXODO', 800.00, 6);

INSERT INTO products (category_id, name, description, price, is_demo, is_promo, sort_order, image_url, is_available) VALUES
(1, 'SINAI', 'Podés dejar indicaciones para cocina.', 11500.00, 1, 0, 1, '/assets/img/sinai.webp', 1),
(1, 'CAIRO', 'Podés dejar indicaciones para cocina.', 14500.00, 1, 0, 2, '/assets/img/cairo.webp', 1),
(1, 'OKLAHOMA', 'Podés dejar indicaciones para cocina.', 13500.00, 1, 0, 3, '/assets/img/oklahoma.webp', 1),
(1, 'LA PROMESA', 'Podés dejar indicaciones para cocina.', 17500.00, 1, 0, 4, '/assets/img/la-promesa.webp', 1),
(2, '2 Clásicas + Papas', 'Promoción de muestra para editar desde el panel.', 20000.00, 1, 1, 5, '/assets/img/burger-demo.png', 0);

-- Los ingredientes específicos no se infieren de las referencias visuales.
-- Se cargan desde el panel antes de habilitar la personalización por ingrediente.

SET FOREIGN_KEY_CHECKS = 1;

