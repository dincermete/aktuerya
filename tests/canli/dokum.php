<?php

declare(strict_types=1);

/*
 * Bir canlı vakayı motorda çalıştırıp ara değerleri döker (yaşlar, bakiye ömürler, destek sonları, dönem toplamları).
 *   ddev exec php tests/canli/dokum.php 13_eski_2019 [--set=kusur=100]
 */
require __DIR__ . '/karsilastir.php';

use DestekTazminat\Destek\Tarih;
use DestekTazminat\Hesap\Girdi;
use DestekTazminat\Hesap\Hesaplayici;
use DestekTazminat\Veri\YasamTablosu;

$vaka = $filtre ?? '01_base';
$g = array_merge(girdiyeCevir($vakalar[$vaka]), $ayarlar);
$n = Girdi::normalize($g);
$s = (new Hesaplayici())->hesapla($g);

echo "== $vaka\n";
printf("olay %s  rapor %s  kazalı doğum %s (%s)\n", Tarih::yaz($n['olayTarihi']), Tarih::yaz($n['raporTarihi']), Tarih::yaz($n['dogumTarihi']), $n['cinsiyet']);
$yas = Tarih::yas($n['dogumTarihi'], $n['olayTarihi']);
printf("kazalı yaş %.4f  e(%d)=%.2f  ömür sonu %s\n", $yas, YasamTablosu::tamYas($yas), YasamTablosu::bakiye($n['tablo'], $n['cinsiyet'], $yas), $s['ozet']['omurSonu'] ?? '-');
foreach ($n['hakSahipleri'] as $h) {
    if ($h['dogumTarihi'] === null) {
        continue;
    }
    $y = Tarih::yas($h['dogumTarihi'], $n['olayTarihi']);
    $cins = $h['tur'] === 'anne' ? 'K' : ($h['tur'] === 'baba' ? 'E' : $h['cinsiyet']);
    printf("  %-5s %-5s doğum %s yaş %.4f e=%.2f hesapla=%s\n", $h['anahtar'], $h['tur'], Tarih::yaz($h['dogumTarihi']), $y,
        YasamTablosu::bakiye($n['tablo'], $cins, $y), $h['hesapla'] ? 'E' : 'H');
}
if ($s['hata']) {
    echo "HATA: {$s['hata']}\n";
    exit(1);
}
echo 'ozet: ', json_encode($s['ozet'], JSON_UNESCAPED_UNICODE), "\n";
echo 'hakSahipleri: ', json_encode($s['hakSahipleri'], JSON_UNESCAPED_UNICODE), "\n";
echo 'parametre: ', json_encode($s['parametre'], JSON_UNESCAPED_UNICODE), "\n";
printf("kazalı geliri  geçmiş %s  aktif %s  pasif %s\n", ...array_map(fn ($x) => number_format($x, 0, ',', '.'), array_values($s['kazaliGeliri'])));
foreach ($s['sonuclar'] as $r) {
    printf("  %-6s geçmiş %12s aktif %12s pasif %12s evl %10s psd %10s toplam %12s\n", $r['id'],
        ...array_map(fn ($x) => number_format($x, 0, ',', '.'), [$r['gecmis'], $r['aktif'], $r['pasif'], $r['evlenmeIndirimi'], $r['psd'], $r['toplam']]));
}
echo "paylaşım:\n";
foreach ($s['paylasim'] as $p) {
    printf("  %s – %s (%.2f yıl) %s\n", $p['bas'], $p['son'], $p['sure'], json_encode($p['paylar'], JSON_UNESCAPED_UNICODE));
}
foreach ($s['notlar'] as $not) {
    echo "  not: $not\n";
}
$dosya = __DIR__ . "/cases/$vaka.html";
if (is_file($dosya)) {
    $canli = canliOku((string) file_get_contents($dosya));
    echo 'canlı süreler (ay): ', json_encode($canli['sure']), "\n";
    echo 'canlı destek: ', json_encode($canli['destek']), "\n";
    echo 'canlı aralıklar: ', json_encode(['kazali' => $canli['kazali'], 'kisi' => $canli['kisi'], 'toplam' => $canli['toplam']]), "\n";
    echo 'canlı uyarılar: ', json_encode($canli['uyari'], JSON_UNESCAPED_UNICODE), "\n";
}
