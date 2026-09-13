<?php

declare(strict_types=1);

require __DIR__ . '/../app/oturum.php';

oturumBaslat();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrfGecerli($_POST['csrf'] ?? null)) {
    $_SESSION = [];
    $c = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $c['path'], 'secure' => $c['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
    header('Location: giris.php', true, 303);
    exit;
}
header('Location: index.php', true, 303);
