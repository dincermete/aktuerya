<?php

declare(strict_types=1);

/*
 * POST /api.php  (JSON gövde veya form alanları) → hesap sonucu JSON.
 */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../app/oturum.php';

use DestekTazminat\Hesap\Hesaplayici;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!girisYapildi()) {
    http_response_code(401);
    echo json_encode(['hata' => 'Oturum açık değil. Lütfen giriş yapın.', 'giris' => 'giris.php'], JSON_UNESCAPED_UNICODE);
    exit;
}
session_write_close();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['hata' => 'Bu adres yalnızca POST isteği kabul eder.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$govde = (string) file_get_contents('php://input');
$girdi = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? json_decode($govde, true) : $_POST;
if (!is_array($girdi)) {
    http_response_code(400);
    echo json_encode(['hata' => 'Girdi okunamadı: geçerli bir JSON gövdesi gönderin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sonuc = (new Hesaplayici())->hesapla($girdi);
if ($sonuc['hata'] !== null) {
    http_response_code(422);
}
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
