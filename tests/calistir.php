<?php

declare(strict_types=1);

/*
 * Bir JSON dosyasındaki girdi dizisini (veya tek girdiyi) motorda çalıştırıp özet yazar; PHP uyarıları dahil görünür.
 *   ddev exec php tests/calistir.php tests/ornek_genis.json
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/../src/bootstrap.php';

use DestekTazminat\Hesap\Hesaplayici;

$veri = json_decode((string) file_get_contents($argv[1] ?? __DIR__ . '/ornek_genis.json'), true, 512, JSON_THROW_ON_ERROR);
$girdiler = array_is_list($veri) ? $veri : [$veri];
foreach ($girdiler as $i => $g) {
    $s = (new Hesaplayici())->hesapla($g);
    echo "== girdi $i ({$g['mod']} · {$g['yontem']} · {$g['tablo']})\n";
    if ($s['hata']) {
        echo "  HATA: {$s['hata']}\n";
        continue;
    }
    foreach ($s['sonuclar'] as $r) {
        printf("  %-8s geçmiş %12s aktif %12s pasif %12s psd %10s gig %10s bakıcı %10s toplam %12s\n", $r['id'],
            ...array_map(fn ($x) => number_format((float) $x, 0, ',', '.'), [$r['gecmis'], $r['aktif'], $r['pasif'], $r['psd'], $r['geciciIsgoremezlik'], $r['bakici'], $r['toplam']]));
    }
    printf("  toplam %s · faiz (%s %s–%s) %s\n", number_format($s['toplam'], 0, ',', '.'), $s['faiz']['tur'], $s['faiz']['bas'], $s['faiz']['son'],
        number_format($s['faiz']['tutar'], 0, ',', '.'));
    echo '  süre: ', json_encode($s['ozet']['sureAy']), "\n";
    foreach ($s['notlar'] as $n) {
        echo "  not: $n\n";
    }
}
