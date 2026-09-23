<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Exodo\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($path)) require_once $path;
});

