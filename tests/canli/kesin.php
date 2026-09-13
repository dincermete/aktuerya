<?php

declare(strict_types=1);

/*
 * olcum.py ile ölçülen kesin canlı değerleri (kusur %100) motorla karşılaştırır.
 *   ddev exec php tests/canli/kesin.php [--set=anahtar=deger ...]
 */
require __DIR__ . '/karsilastir.php';

use DestekTazminat\Hesap\Hesaplayici;

$olcumler = [];
foreach (glob(__DIR__ . '/olcum_sonuc*.json') ?: [] as $dosya) {
    foreach (json_decode((string) file_get_contents($dosya), true) ?: [] as $k => $v) {
        if ($v !== null || !isset($olcumler[$k])) {
            $olcumler[$k] = $v;
        }
    }
}
$motor = new Hesaplayici();

printf("%-34s %-14s %14s %14s %9s\n", 'vaka', 'kalem', 'canlı (kesin)', 'motor', 'fark %');
foreach ($olcumler as $anahtar => $aralik) {
    [$vaka, $kalem] = explode('|', $anahtar);
    if ($aralik === null || !isset($vakalar[$vaka]) || ($filtre && !str_contains($vaka, $filtre))) {
        continue;
    }
    // "kalem@n": olcum.py %100'deki alt sınırın n × 250 bin altındaki sınırı kullandı
    [$kalem, $kaydir] = explode('@', $kalem) + [1 => '0'];
    // olcum.py, %100 kusurdaki aralığın alt sınırını (S) geçen kusuru (k*) bulur ve V = S × 100 ÷ k* bildirir.
    // Motor da k* kusurunda çalıştırılıp aynı dönüşümle karşılaştırılır; kusurla çarpılmayan kalemler (yetiştirme gideri, trafik ödemesi) de böylece doğru ölçülür.
    [$alt0, $ust0] = $aralik ?? [0, 0];
    $sinir = floor((($alt0 + $ust0) / 2) / 250000) * 250000 - (int) $kaydir * 250000;
    $kYildiz = $alt0 > 0 && $sinir > 0 ? $sinir * 100 / (($alt0 + $ust0) / 2) : 100.0;
    $g = array_merge(girdiyeCevir($vakalar[$vaka]), ['kusur' => $kYildiz], $ayarlar);
    $s = $motor->hesapla($g);
    [$tur, $ad] = explode('.', $kalem) + [1 => null];
    // İş gücü kaybında canlı sonuç satırları sırası: geçmiş, aktif, pasif, [bakıcı], [geçici iş göremezlik], [PSD / trafik ödemesi], toplam
    $isgucuSatirlari = [];
    if (($s['mod'] ?? '') === 'isgucu' && isset($s['sonuclar'][0])) {
        $r0 = $s['sonuclar'][0];
        $isgucuSatirlari = [$r0['gecmis'], $r0['aktif'], $r0['pasif']];
        if ($g['bakiciHesapla'] ?? false) {
            $isgucuSatirlari[] = $r0['bakici'];
        }
        if ($g['geciciHesapla'] ?? false) {
            $isgucuSatirlari[] = $r0['geciciIsgoremezlik'];
        }
        if ($r0['psd'] > 0) {
            $isgucuSatirlari[] = $r0['psd'];
        }
        $isgucuSatirlari[] = $r0['toplam'];
    }
    $bizim = match ($tur) {
        'faiz' => $s['faiz']['tutar'] ?? null,
        'once' => $ad === 'son' ? ($isgucuSatirlari ? end($isgucuSatirlari) : null) : ($isgucuSatirlari[(int) $ad] ?? null),
        'kazali' => $s['kazaliGeliri'][$ad] ?? null,
        'kisi' => (function () use ($s, $ad) {
            foreach ($s['sonuclar'] as $r) {
                if ($r['id'] === $ad) {
                    return $r['toplam'];
                }
            }

            return 0.0;
        })(),
        default => $s['toplam'],
    };
    $bizim = $bizim === null ? null : (float) $bizim * 100 / $kYildiz;
    [$alt, $ust] = $aralik;
    $canli = ($alt + $ust) / 2;
    $belirsiz = $alt == 0 ? ' (yalnız üst sınır)' : '';
    printf("%-34s %-14s %14s %14s %8s%%%s\n", $vaka, $kalem, number_format($canli, 0, ',', '.'), number_format((float) $bizim, 0, ',', '.'),
        $alt == 0 ? '-' : number_format(100 * ((float) $bizim - $canli) / $canli, 2, ',', ''), $belirsiz);
}
