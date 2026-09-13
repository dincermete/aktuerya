<?php

declare(strict_types=1);

/*
 * Komut satırı: JSON girdi → JSON sonuç.
 *   ddev exec php bin/hesapla.php ornek.json
 *   echo '{"olayTarihi":"2023-03-14",...}' | ddev exec php bin/hesapla.php
 */
require __DIR__ . '/../src/bootstrap.php';

use DestekTazminat\Hesap\Hesaplayici;

$kaynak = $argv[1] ?? 'php://stdin';
$girdi = json_decode((string) file_get_contents($kaynak), true);
if (!is_array($girdi)) {
    fwrite(STDERR, "Geçerli bir JSON girdi verin.\n");
    exit(1);
}

$sonuc = (new Hesaplayici())->hesapla($girdi);
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION), "\n";
exit($sonuc['hata'] === null ? 0 : 2);
