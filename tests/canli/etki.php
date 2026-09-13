<?php

declare(strict_types=1);

/*
 * Canlı yanıtların sonuç bölümünü ve uyarılarını düz metin olarak gösterir (yeni parametrelerin etkisini görmek için).
 *   ddev exec php tests/canli/etki.php 01_base 45_faiz_avans ...
 */
require __DIR__ . '/karsilastir.php';

foreach (array_slice($argv, 1) as $vaka) {
    if (str_starts_with($vaka, '--')) {
        continue;
    }
    $dosya = __DIR__ . "/cases/$vaka.html";
    if (!is_file($dosya)) {
        echo "== $vaka: yanıt yok\n";
        continue;
    }
    $t = metin((string) file_get_contents($dosya));
    $bas = mb_strpos($t, 'S O N U Ç L A R');
    $son = mb_strpos($t, 'İşbu web uygulaması');
    $bolum = $bas === false ? '(sonuç bölümü yok)' : mb_substr($t, $bas, ($son === false ? 3000 : $son - $bas));
    $bolum = preg_replace('/\s*\n\s*/u', ' | ', trim($bolum)) ?? '';
    $bolum = preg_replace('/(\| )+/u', '| ', $bolum) ?? '';
    echo "== $vaka\n  $bolum\n";
    $canli = canliOku((string) file_get_contents($dosya));
    foreach ($canli['uyari'] as $u) {
        if (!str_contains($u, 'Farazi Birinci') && !str_contains($u, 'Farazi İkinci') && !str_contains($u, 'G.V. İstisnası Düşülmüş Asgari Ücretten YÜKSEKTİR')) {
            echo "  ! $u\n";
        }
    }
    echo '  süre: ', json_encode($canli['sure']), '  destek: ',
        json_encode(array_filter($canli['destek'], fn ($d) => $d['yil'] > 0 || $d['yas'] > 0)), "\n";
}
