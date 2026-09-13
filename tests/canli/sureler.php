<?php

declare(strict_types=1);

/*
 * Ekranda gösterilen sürelerin (ay) canlı sitede hangi yuvarlama kuralıyla üretildiğini bulur.
 * Her ölçüt için aday formülleri tüm vakalarda dener, tutan vaka sayısını yazar.
 *   ddev exec php tests/canli/sureler.php [--detay]
 */
require __DIR__ . '/karsilastir.php';

use DestekTazminat\Destek\Tarih;
use DestekTazminat\Hesap\Girdi;
use DestekTazminat\Veri\YasamTablosu;

$detay = in_array('--detay', $argv, true);

/** Aynı iki tarih için aday ay sayıları */
function adaylar(int $bas, int $son): array
{
    if ($son <= $bas) {
        return array_fill_keys(['takvimTaban', 'takvimYuvarla15', 'takvimTavan', 'gun30_4Taban', 'gun30_4Yuvarla', 'yil12Taban', 'yil12Yuvarla', 'gun30Taban', 'gun30Yuvarla', 'takvimArtiBir'], 0);
    }
    [$y1, $a1, $g1] = Tarih::parcala($bas);
    [$y2, $a2, $g2] = Tarih::parcala($son);
    $ay = ($y2 - $y1) * 12 + ($a2 - $a1);
    $gunFark = $g2 - $g1;
    $taban = $gunFark < 0 ? $ay - 1 : $ay;
    $artanGun = $son - Tarih::ayEkle($bas, $taban);
    $gun = $son - $bas;

    return [
        'takvimTaban' => $taban,
        'takvimYuvarla15' => $taban + ($artanGun >= 15 ? 1 : 0),
        'takvimTavan' => $taban + ($artanGun > 0 ? 1 : 0),
        'takvimArtiBir' => Tarih::ayFarki($bas, $son + 1),
        'gun30_4Taban' => (int) floor($gun / 30.4375),
        'gun30_4Yuvarla' => (int) round($gun / 30.4375),
        'yil12Taban' => (int) floor($gun / 365.25 * 12 + 1e-9),
        'yil12Yuvarla' => (int) round($gun / 365.25 * 12),
        'gun30Taban' => intdiv($gun, 30),
        'gun30Yuvarla' => (int) round($gun / 30),
    ];
}

$sayac = [];
$vakaSayisi = 0;
foreach ($vakalar as $etiket => $c) {
    $dosya = __DIR__ . "/cases/$etiket.html";
    if (!is_file($dosya)) {
        continue;
    }
    $canli = canliOku((string) file_get_contents($dosya))['sure'];
    if (!$canli) {
        continue;
    }
    $vakaSayisi++;
    $g = Girdi::normalize(girdiyeCevir($c));
    $O = $g['olayTarihi'];
    $R = $g['raporTarihi'];
    $KB = $g['dogumTarihi'];
    $yas = Tarih::yas($KB, $O);
    $e = YasamTablosu::bakiye($g['tablo'], $g['cinsiyet'], $yas, $g['yasYukariYuvarla']);
    $Lyil = Tarih::yilEkle($O, $e);
    $Lgun = $O + (int) round($e * 365.25);
    $Lgun365 = $O + (int) round($e * 365);
    $gelirBas = Tarih::yilEkle($KB, $g['aktifBaslamaYasi']);
    $A = min(Tarih::yilEkle($KB, $g['aktifBitisYasi']), $Lyil);
    $G = $g['gecmisRaporTarihiIle'] ? $R : Tarih::ymd(Tarih::yil($R) + 1, 1, 1);
    $G = max($O, min($G, $Lyil));

    $olcutler = [];
    foreach (adaylar($KB, $O) as $k => $v) {
        $olcutler['olayYasi'][$k] = $v;
    }
    foreach (adaylar($O, $G) as $k => $v) {
        $olcutler['gecmisDonem'][$k] = $v;
        $olcutler['gecmisDonemSon-1'][$k] = adaylar($O, $G - 1)[$k];
    }
    foreach (adaylar($G, $A) as $k => $v) {
        $olcutler['aktifDonem'][$k] = $v;
    }
    foreach (['Lyil' => $Lyil, 'Lgun' => $Lgun, 'Lgun365' => $Lgun365] as $lAd => $L) {
        foreach (adaylar(max($A, $G), $L) as $k => $v) {
            $olcutler['pasifDonem']["$lAd/$k"] = $v;
        }
        foreach (adaylar($O, $L) as $k => $v) {
            $olcutler['bakiyeOmur']["$lAd/$k"] = $v;
        }
        foreach (adaylar(max($O, $gelirBas), min($A, $L)) as $k => $v) {
            $olcutler['faalCalisma']["$lAd/$k"] = $v;
        }
    }
    // 365 günlük yıl (artık yıl yok sayılır) ile yaş: canlı site yaşları böyle hesaplıyor olabilir
    $yas365 = ($O - $KB) / 365;
    $aktifSonYas = (float) $g['aktifBitisYasi'];
    $olcutler['olayYasi']['yas365Taban'] = (int) floor($yas365 * 12 + 1e-9);
    $olcutler['olayYasi']['yas365Yuvarla'] = (int) round($yas365 * 12);
    foreach (['Taban' => 0.0, 'Taban+0.05' => 0.05, 'Yuvarla' => 0.5] as $ek => $eps) {
        $olcutler['faalCalisma']["yas365/$ek"] = max(0, (int) floor(($aktifSonYas - max($yas365, (float) $g['aktifBaslamaYasi'])) * 12 + $eps));
        $pasifYil = $yas365 >= $aktifSonYas ? $e : $e + $yas365 - $aktifSonYas;
        $olcutler['pasifDonem']["yas365/$ek"] = max(0, (int) floor($pasifYil * 12 + $eps));
        $olcutler['pasifDonem']["yas365-gecmisTasma/$ek"] = $yas365 >= $aktifSonYas
            ? max(0, (int) floor($e * 12 + $eps) - adaylar($O, $G)['yil12Taban'])
            : max(0, (int) floor($pasifYil * 12 + $eps));
        $olcutler['gecmisDonem']["gun365/$ek"] = (int) floor(($G - $O) / 365 * 12 + $eps);
        $olcutler['aktifDonem']["gun365/$ek"] = max(0, (int) floor(($A - $G) / 365 * 12 + $eps));
    }
    $olcutler['bakiyeOmur']['e12Taban'] = (int) floor($e * 12 + 1e-9);
    $olcutler['bakiyeOmur']['e12Taban+0.05'] = (int) floor($e * 12 + 0.05);
    $olcutler['bakiyeOmur']['e12Yuvarla'] = (int) round($e * 12);
    foreach (array_keys(adaylar($O, $G)) as $k) {
        $olcutler['faalCalisma']["gecmis+aktif/$k"] = adaylar($O, $G)[$k] + adaylar($G, $A)[$k];
        $olcutler['pasifDonem']["bakiye-gecmis-aktif/$k"] = adaylar($O, $Lyil)[$k] - adaylar($O, $G)[$k] - adaylar($G, $A)[$k];
    }

    foreach ($olcutler as $olcut => $formuller) {
        if (!isset($canli[$olcut === 'gecmisDonemSon-1' ? 'gecmisDonem' : $olcut])) {
            continue;
        }
        $hedef = $canli[$olcut === 'gecmisDonemSon-1' ? 'gecmisDonem' : $olcut];
        foreach ($formuller as $ad => $deger) {
            $sayac[$olcut][$ad] ??= ['tutan' => 0, 'tutmayan' => []];
            if ($deger === $hedef) {
                $sayac[$olcut][$ad]['tutan']++;
            } else {
                $sayac[$olcut][$ad]['tutmayan'][] = "{$etiket}({$deger}≠{$hedef})";
            }
        }
    }
}

foreach ($sayac as $olcut => $formuller) {
    uasort($formuller, fn ($a, $b) => $b['tutan'] <=> $a['tutan']);
    echo "\n== $olcut ($vakaSayisi vaka)\n";
    foreach (array_slice($formuller, 0, $detay ? 40 : 4, true) as $ad => $x) {
        printf("  %2d  %-40s %s\n", $x['tutan'], $ad, implode(' ', array_slice($x['tutmayan'], 0, 6)));
    }
}
