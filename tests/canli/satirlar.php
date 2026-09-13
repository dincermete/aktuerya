<?php

declare(strict_types=1);

/*
 * Bir vakanın satırlarını, o satırda pay alan kişi kümesine göre gruplar: grup başına paylaştırılan gelir tabanı ve kişi payları.
 * Paylaştırma kurallarını kesin ölçümden geri çözmek için kullanılır.
 *   ddev exec php tests/canli/satirlar.php 104_ayir_3cocuk_anababa [--set=_ayir=iki]
 */
require __DIR__ . '/karsilastir.php';

use DestekTazminat\Hesap\Hesaplayici;

$vaka = $filtre ?? '01_base';
$g = array_merge(girdiyeCevir($vakalar[$vaka]), ['kusur' => 100], $ayarlar);
$s = (new Hesaplayici())->hesapla($g);

$oran = function (string $etiket): float {
    $e = str_replace(',', '.', $etiket);
    if (str_starts_with($e, '%')) {
        return (float) substr($e, 1) / 100;
    }
    [$a, $b] = explode('/', $e) + [1 => '1'];

    return (float) $a / (float) $b;
};

$gruplar = [];
foreach ($s['tablolar'] as $tablo => $satirlar) {
    foreach ($satirlar as $satir) {
        $kisiler = $satir['kisiler'] ?? [];
        if (!$kisiler) {
            continue;
        }
        ksort($kisiler);
        $imza = implode(',', array_keys($kisiler));
        $gr = &$gruplar[$imza];
        $gr ??= ['satir' => 0, 'taban' => 0.0, 'paylar' => [], 'tutar' => []];
        $gr['satir']++;
        $ilk = array_key_first($kisiler);
        $o = $oran((string) $kisiler[$ilk]['etiket']);
        $gr['taban'] += $o > 0 ? $kisiler[$ilk]['tutar'] / $o : 0.0;
        foreach ($kisiler as $id => $x) {
            $gr['paylar'][$id] = $x['etiket'];
            $gr['tutar'][$id] = ($gr['tutar'][$id] ?? 0.0) + $x['tutar'];
        }
        unset($gr);
    }
}
echo "== $vaka\n";
foreach ($gruplar as $imza => $gr) {
    printf("%-28s satır %3d  taban %14s  ", $imza, $gr['satir'], number_format($gr['taban'], 0, ',', '.'));
    foreach ($gr['paylar'] as $id => $p) {
        printf('%s %s (%s)  ', $id, $p, number_format($gr['tutar'][$id], 0, ',', '.'));
    }
    echo "\n";
}
foreach ($s['sonuclar'] as $r) {
    printf("  %-6s toplam %14s  evlenme %12s\n", $r['id'], number_format($r['toplam'], 0, ',', '.'), number_format($r['evlenmeIndirimi'], 0, ',', '.'));
}
