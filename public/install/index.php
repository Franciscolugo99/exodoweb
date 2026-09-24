<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$configPath = $root . DIRECTORY_SEPARATOR . 'config.php';
$lockPath = __DIR__ . DIRECTORY_SEPARATOR . 'install.lock';
$error = '';
$success = false;

if (is_file($lockPath) || is_file($configPath)) {
    $alreadyInstalled = true;
} else {
    $alreadyInstalled = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $port = (int) ($_POST['db_port'] ?? 3306);
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $pass = (string) ($_POST['db_pass'] ?? '');
        $owner = trim((string) ($_POST['owner_username'] ?? ''));
        $ownerPass = (string) ($_POST['owner_password'] ?? '');

        if ($host === '' || $port < 1 || $port > 65535 || $name === '' || $user === '') {
            $error = 'Completá correctamente los datos de MySQL/MariaDB.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $owner)) {
            $error = 'El usuario owner debe tener entre 3 y 50 caracteres válidos.';
        } elseif (strlen($ownerPass) < 10) {
            $error = 'La contraseña del owner debe tener al menos 10 caracteres.';
        } else {
            try {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                $schemaPath = $root . DIRECTORY_SEPARATOR . 'schema.sql';
                if (!is_file($schemaPath)) throw new RuntimeException('No se encontró schema.sql.');
                $schema = file_get_contents($schemaPath);
                if ($schema === false || trim($schema) === '') throw new RuntimeException('schema.sql está vacío.');
                $pdo->exec($schema);

                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, active) VALUES (?, ?, \'owner\', 1)');
                $stmt->execute([$owner, password_hash($ownerPass, PASSWORD_DEFAULT)]);

                $config = [
                    'db' => [
                        'host' => $host,
                        'port' => $port,
                        'name' => $name,
                        'user' => $user,
                        'pass' => $pass,
                    ],
                ];
                $configCode = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
                if (file_put_contents($configPath, $configCode, LOCK_EX) === false) {
                    throw new RuntimeException('No se pudo guardar config.php.');
                }
                if (file_put_contents($lockPath, 'installed ' . gmdate('c') . "\n", LOCK_EX) === false) {
                    @unlink($configPath);
                    throw new RuntimeException('No se pudo crear el bloqueo del instalador.');
                }
                @chmod($configPath, 0640);
                $success = true;
            } catch (Throwable $e) {
                $error = 'No se pudo completar la instalación: ' . $e->getMessage();
            }
        }
    }
}
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/png" href="/assets/img/logo-exodo-transparent.png"><title>Instalar ÉXODO</title>
<style>
:root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#1c1c1c;color:#f5f0e8}body{max-width:680px;margin:0 auto;padding:clamp(1rem,5vw,3rem)}main{background:#262626;border-top:4px solid #e8501a;border-radius:10px;padding:clamp(1rem,4vw,2rem)}h1{margin-top:0;letter-spacing:.04em}h2{font-size:1.05rem;margin:1.5rem 0 .6rem;color:#f4a17f}.grid{display:grid;grid-template-columns:2fr 1fr;gap:.8rem}label{display:grid;gap:.35rem;margin:.65rem 0;font-size:.9rem;color:#c9c1b8}input{width:100%;box-sizing:border-box;background:#171717;border:1px solid #555;color:#fff;border-radius:6px;padding:.75rem;font-size:1rem}button{margin-top:1rem;background:#e8501a;border:0;color:#fff;border-radius:6px;padding:.8rem 1.1rem;font-weight:700;font-size:1rem;cursor:pointer}.error{background:#5a2525;color:#ffd1c6;padding:.8rem;border-radius:6px}.success{background:#1e4b32;color:#c7f4d4;padding:.8rem;border-radius:6px}small{color:#b8b2a8;line-height:1.5}a{color:#f4a17f}
</style></head>
<body><main>
<h1>ÉXODO · Instalación</h1>
<?php if ($success): ?><div class="success"><strong>Instalación completada.</strong><br>Ya podés abrir el sitio y entrar al panel con el usuario owner creado.</div><p><a href="/">Ir al sitio</a> · <a href="/admin/">Abrir panel</a></p>
<?php elseif ($alreadyInstalled): ?><div class="success">ÉXODO ya está instalado. El instalador quedó bloqueado.</div><p><a href="/">Ir al sitio</a> · <a href="/admin/">Abrir panel</a></p>
<?php else: ?>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<p><small>Creá primero la base de datos vacía. El instalador crea las tablas, carga el catálogo de muestra y genera el primer usuario owner. Las credenciales quedan fuera del repositorio.</small></p>
<form method="post" autocomplete="off">
<h2>Base de datos</h2><div class="grid"><label>Host<input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Puerto<input name="db_port" type="number" min="1" max="65535" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>" required></label></div>
<label>Base de datos<input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
<label>Usuario MySQL<input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
<label>Contraseña MySQL<input name="db_pass" type="password"></label>
<h2>Usuario inicial del panel</h2>
<label>Usuario owner<input name="owner_username" value="<?= htmlspecialchars($_POST['owner_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
<label>Contraseña owner<input name="owner_password" type="password" minlength="10" required></label>
<button type="submit">Instalar ÉXODO</button>
</form>
<?php endif; ?></main></body></html>

