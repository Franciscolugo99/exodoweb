-- Ejecutar una sola vez en instalaciones creadas antes de agregar extras.
ALTER TABLE product_ingredients
    ADD COLUMN is_addable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default;

ALTER TABLE order_items
    ADD COLUMN added_ingredients TEXT DEFAULT NULL
    COMMENT 'JSON de extras y precios al momento de la compra'
    AFTER removed_ingredients;

INSERT INTO product_ingredients (product_id, ingredient_id, is_default, is_addable)
SELECT p.id, i.id, 0, 1
FROM products p
JOIN ingredients i ON i.name IN ('Cheddar', 'Bacon')
WHERE p.name IN ('SINAI', 'CAIRO', 'OKLAHOMA', 'LA PROMESA')
ON DUPLICATE KEY UPDATE is_addable = 1;
