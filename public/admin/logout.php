<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

\Exodo\Auth::logout();
header('Location: /admin/');
exit;

