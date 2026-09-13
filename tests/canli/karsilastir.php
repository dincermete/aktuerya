<?php

declare(strict_types=1);

/*
 * Canlı site (ercanerdem.av.tr) yanıtlarıyla motoru karşılaştırır.
 *   1) python tests/canli/batch.py        → cases/*.html (canlı yanıtlar)
 *   2) ddev exec php tests/canli/karsilastir.php
 * Üye olmayan kullanıcıya canlı site tutarları 250.000 TL'lik aralıklarla, süreleri tam değer olarak gösterir.
 */
require __DIR__ . '/../../src/bootstrap.php';

use DestekTazminat\Destek\Sayi;
use DestekTazminat\Hesap\Hesaplayici;

$dizin = __DIR__ . '/cases';
$vakalar = json_decode((string) file_get_contents($dizin . '/cases.json'), true);
// Kullanım: karsilastir.php [vaka-filtresi] [--ozet] [--set=_yuzde70=b] [--set=_sinirFazlasi=kazali]
$filtre = null;
$ozetMod = false;
$ayarlar = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--set=')) {
        [$k, $deger] = explode('=', substr($arg, 6), 2) + [1 => ''];
        $ayarlar[$k] = $deger;
    } elseif ($arg === '--ozet') {
        $ozetMod = true;
    } else {
        $filtre = $arg;
    }
}

const SATIR_ONEKLERI = [
    'es' => ['Checkboxes', 'Es', null, null],
    'c1' => ['Checkbox1', 'Bir', 'Bir', 'YukCheckbox1'],
    'c2' => ['Checkbox2', 'Iki', 'Iki', 'YukCheckbox2'],
    'c3' => ['Checkbox3', 'Uc', 'Uc', 'YukCheckbox3'],
    'c4' => ['Checkbox4', 'Dort', 'Dort', 'YukCheckbox4'],
    'c5' => ['Checkbox5', 'Bes', 'Bes', 'YukCheckbox5'],
    'ana' => ['Checkbox6', 'Ana', 'Ana', 'YukCheckbox6'],
    'baba' => ['Checkbox7', 'Baba', 'Baba', 'YukCheckbox7'],
];

/** Canlı form alanlarını motor girdisine çevirir. */
function girdiyeCevir(array $c): array
{
    $g = [
        'mod' => ($c['RadioGroup4'] ?? '') === 'Radio9' ? 'isgucu' : 'destek',
        'kazaTuru' => ($c['RadioGroup40'] ?? '') === 'Radio25' ? 'trafik' : 'is',
        'yontem' => ($c['RadioGroup'] ?? '') === 'Radio11' ? 'aktuer' : 'progresif',
        'tablo' => ($c['RadioGroup3'] ?? '') === 'Radio6' ? 'pmf' : 'trh',
        'paylastirma' => match ($c['RadioGroup2'] ?? '') { 'Radio4' => 'ayim', 'Radio5' => '70', default => '21' },
        'kusur' => $c['DavaliKusurF'] ?? 100,
        'olayTarihi' => $c['OlayTarF'] ?? null,
        'dogumTarihi' => $c['DogTarF'] ?? null,
        'cinsiyet' => $c['KendiCinsF'] ?? 'E',
        'raporTarihi' => $c['RapTarF'] ?? null,
        'ucret' => $c['MaasF'] ?? 0,
        'ilaveAgi' => isset($c['Checkboxagi']),
        'anneBabaYerineCocuk' => ($c['RadioGroup20'] ?? '') === 'Radio21',
        'evlenmeOlayTarihine' => isset($c['Checkboxihtimal']),
        'anneBabaYarimPay' => isset($c['Checkboxanababayari']),
        'anneBaba25SinirYok' => isset($c['Checkboxanababa25yok']),
        'gecmisRaporTarihiIle' => isset($c['Checkboxgecdonraptar']),
        // Canlı alan adı "AskerDegil": işaretliyse askerlik düşülmez (15_askerlik: geçmiş dönem base ile aynı ölçüldü)
        'askerlikDus' => !isset($c['CheckboxAskerDegil']),
        'agisiz' => isset($c['CheckboxAgisiz']),
        'esCalisiyor' => isset($c['Checkboxagies']),
        'yasYukariYuvarla' => isset($c['Checkboxyasyuvarla']),
        'bakiyeRaporTarihine' => isset($c['Checkboxraptarhesapla']),
    ];
    foreach (['teknikfaizF' => 'teknikFaiz', 'IsGorOranF' => 'isgucuOrani', 'KendiPSDF' => 'kazaliPsd', 'artisoranF' => 'artis', 'iskontooranF' => 'iskonto',
        'gelirbaslaF' => 'aktifBaslamaYasi', 'aktifdonsonF' => 'aktifBitisYasi', 'EmMaasF' => 'pasifGelir', 'erkekdessonF' => 'erkekDestekSonu',
        'kizdessonF' => 'kizDestekSonu', 'unidessonF' => 'universiteDestekSonu', 'askerlikyasiF' => 'askerlikYasi', 'askersureF' => 'askerlikSuresi',
        'giggunF' => 'geciciGun', 'kazaligigF' => 'gio', 'bakicioranF' => 'bakiciOrani', 'bakicigunF' => 'bakiciGun'] as $canli => $motor) {
        if (isset($c[$canli])) {
            $g[$motor] = $c[$canli];
        }
    }
    // Canlı istekte gönderilmeyen alanlar formun HTML varsayılanıyla gitti (farazi 0, oran 0, faiz başlangıcı 1.01.2016)
    $g['faraziEvlenme'] = $c['farazievlenmeF'] ?? '0';
    $g['farazi1Cocuk'] = $c['farazibirinciF'] ?? '0';
    $g['farazi2Cocuk'] = $c['faraziikinciF'] ?? '0';
    $g += [
        'aylikBaz' => isset($c['Checkboxaylikbaz']),
        'gelirVergisiz' => isset($c['CheckboxGVistisna']),
        'gecmisGercekUcret' => isset($c['Checkboxgecdongercek']),
        'esCocukAyri' => isset($c['Checkboxyenipaylasim']),
        'tamHayat' => isset($c['Checkboxtamhayat']),
        'geciciHesapla' => isset($c['Checkboxgig']),
        'bakiciHesapla' => isset($c['Checkboxbakici']),
        'bakiciNet' => isset($c['Checkboxbakicinet']),
        'yetistirme' => isset($c['Checkboxyetistirme']),
        'yetistirmeAnneYok' => isset($c['CheckboxyetistirmeAnne']),
        'yetistirmeOrani' => $c['kardessayF'] ?? '0',
        'yetistirmeGelir' => $c['yetismaasF'] ?? '0',
        'faizBaslangic' => $c['faizbaslaF'] ?? '1.01.2016',
        'faizTuru' => ($c['RadioGroup30'] ?? '') === 'Radio23' ? 'avans' : 'yasal',
        'policeTarihi' => $c['tanzimtarihF'] ?? null,
        'kazaliSgkGelir' => $c['KendiSGKF'] ?? '0',
        'kazaliSgkBaslangic' => $c['kazaligelbaslaF'] ?? null,
        'sgkBaslangic' => $c['gelbaslaF'] ?? null,
        // Canlı site SGK aylık gelirinden otomatik PSD'yi üye olmayan isteklerde uygulamıyor (61, 83–86 ölçüldü);
        // karşılaştırmada kapatılır, motorda varsayılan açıktır. Rapor bu vakaları ayrıca belirtir.
        '_sgkPsdOtomatik' => false,
        // Canlı site yasal faizde 31.07.2026'dan itibaren geçerli %31'i henüz uygulamıyor (46_faiz_bas_2025 %24 ile birebir ölçüldü)
        '_faizVeriSonu' => '2026-07-30',
    ];
    $g['tablo'] = match ($c['RadioGroup3'] ?? '') { 'Radio6' => 'pmf', 'Radio7' => 'cso', 'Radio83' => 'gatt', default => 'trh' };
    foreach (SATIR_ONEKLERI as $anahtar => [$kutu, $onek, $cinsOnek, $yuk]) {
        $g['hakSahipleri'][$anahtar] = [
            'hesapla' => isset($c[$kutu]),
            'dogumTarihi' => $c[$onek . 'DogTarF'] ?? null,
            'cinsiyet' => $cinsOnek ? ($c[$cinsOnek . 'CinsF'] ?? ($anahtar === 'ana' ? 'K' : 'E')) : null,
            'yuksekOgrenim' => $yuk ? isset($c[$yuk]) : false,
            'psd' => $c[$onek . 'PSDF'] ?? 0,
            'sgkGelir' => $c[$onek . 'SGKF'] ?? 0,
            'destekSonu' => $c[$onek . 'DesTarF'] ?? null,
        ];
        if ($g['hakSahipleri'][$anahtar]['cinsiyet'] === null) {
            unset($g['hakSahipleri'][$anahtar]['cinsiyet']);
        }
    }

    return $g;
}

function metin(string $html): string
{
    $s = preg_replace('#<(script|style)\b.*?</\1>#is', '', $html) ?? '';
    $s = preg_replace('#<br\s*/?>|</tr>|</p>|</div>#i', "\n", $s) ?? '';
    // strip_tags canlı sayfadaki kapanmamış bir "<" işaretinde metnin kalanını siliyor; bu yüzden regex.
    $s = preg_replace('#<[^>]*>#', ' ', $s) ?? '';
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/[ \t\r\x{00A0}]+/u', ' ', $s) ?? '';

    return preg_replace('/\n\s*\n+/', "\n", $s) ?? '';
}

/** @return list<array{lo:int,hi:int,pos:int}> */
function araliklar(string $t, int $bas = 0, ?int $son = null): array
{
    preg_match_all('/([\d.]+) TL - ([\d.]+) TL Arası/u', $t, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $bas);
    $out = [];
    foreach ($m as $x) {
        if ($son !== null && $x[0][1] > $son) {
            break;
        }
        $out[] = ['lo' => (int) str_replace('.', '', $x[1][0]), 'hi' => (int) str_replace('.', '', $x[2][0]), 'pos' => $x[0][1]];
    }

    return $out;
}

function canliOku(string $html): array
{
    $t = metin($html);
    $r = ['sure' => [], 'destek' => [], 'kazali' => [], 'kisi' => [], 'toplam' => null, 'faiz' => null, 'uyari' => [], 'araliklar' => []];
    if (preg_match('/İşlemiş (?:Yasal|Avans) Faiz\s*:\s*([\d.]+) TL - ([\d.]+) TL Arası/u', $t, $fm)) {
        $r['faiz'] = ['lo' => (int) str_replace('.', '', $fm[1]), 'hi' => (int) str_replace('.', '', $fm[2]), 'pos' => 0];
    }
    foreach (['olayYasi' => 'Olay Tarihi Yaş', 'bakiyeOmur' => 'Bakiye Ömür', 'faalCalisma' => 'Faal Çalışma', 'gecmisDonem' => 'Geçmiş Dönem',
        'aktifDonem' => 'Gelecek Aktif Dönem', 'pasifDonem' => 'Gelecek Pasif Dönem'] as $k => $etiket) {
        if (preg_match('/' . preg_quote($etiket, '/') . ' :\s*(\d+) Yıl (\d+) Ay/u', $t, $m)) {
            $r['sure'][$k] = (int) $m[1] * 12 + (int) $m[2];
        }
    }
    preg_match_all('/Yaş: (\d+) - Destek \(Yıl\): (\d+)/u', $t, $m, PREG_SET_ORDER);
    foreach (array_keys(SATIR_ONEKLERI) as $i => $anahtar) {
        if (isset($m[$i])) {
            $r['destek'][$anahtar] = ['yas' => (int) $m[$i][1], 'yil' => (int) $m[$i][2]];
        }
    }
    $sonuc = mb_strpos($t, 'S O N U Ç L A R');
    $faiz = $sonuc !== false ? mb_strpos($t, 'İşlemiş Yasal Faiz', $sonuc) : false;
    if ($sonuc !== false) {
        $bayt = strlen(mb_substr($t, 0, $sonuc));
        $faizBayt = $faiz !== false ? strlen(mb_substr($t, 0, $faiz)) : null;
        $hepsi = araliklar($t, $bayt, $faizBayt);
        $r['araliklar'] = array_map(fn ($a) => [$a['lo'], $a['hi']], $hepsi);
        $dagilim = strpos($t, 'DESTEK PAYLARINA GÖRE DAĞILIM', $bayt);
        if ($dagilim !== false) {
            $once = array_values(array_filter($hepsi, fn ($a) => $a['pos'] < $dagilim));
            $sonra = array_values(array_filter($hepsi, fn ($a) => $a['pos'] > $dagilim));
            foreach (['gecmis', 'aktif', 'pasif'] as $i => $k) {
                if (isset($once[$i])) {
                    $r['kazali'][$k] = $once[$i];
                }
            }
            foreach (array_keys(SATIR_ONEKLERI) as $i => $anahtar) {
                if (isset($sonra[$i])) {
                    $r['kisi'][$anahtar] = $sonra[$i];
                }
            }
            $r['toplam'] = $sonra[8] ?? null;
        } else {
            $r['toplam'] = $hepsi ? $hepsi[count($hepsi) - 1] : null;
            foreach (['gecmis', 'aktif', 'pasif'] as $i => $k) {
                if (isset($hepsi[$i]) && count($hepsi) > 3) {
                    $r['kazali'][$k] = $hepsi[$i];
                }
            }
        }
    }
    if (preg_match('/UYARILAR :(.*?)(YAZDIRILABİLİR|$)/su', $t, $m)) {
        foreach (explode("\n", $m[1]) as $satir) {
            $satir = trim($satir);
            if (str_starts_with($satir, '*') && !str_contains($satir, 'ÜYE OLMADIĞINIZ')) {
                $r['uyari'][] = ltrim($satir, '* ');
            }
        }
    }

    return $r;
}

function tlKisa(float $x): string
{
    return number_format($x / 1000, 0, ',', '.') . ' bin';
}

// Başka bir betik (kesin.php) yalnızca fonksiyonları kullanmak için dahil ettiyse burada dur.
if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'karsilastir.php') {
    return;
}

$motor = new Hesaplayici();
$ozet = [];
$md = ["# Canlı site karşılaştırması", '', 'Oluşturma: ' . date('d.m.Y H:i'), '',
    'Tutarlar canlıda 250.000 TL aralık olarak görünür; motor sonucu aralığa düşüyorsa **tuttu** sayılır. Süreler ay, destek yılları tam yıl olarak karşılaştırılır.', ''];
$genel = ['tutar' => [0, 0], 'sure' => [0, 0], 'destek' => [0, 0]];

foreach ($vakalar as $etiket => $c) {
    if ($filtre && !str_contains($etiket, $filtre)) {
        continue;
    }
    $dosya = "$dizin/$etiket.html";
    if (!is_file($dosya)) {
        echo "-- $etiket: canlı yanıt yok\n";
        continue;
    }
    $canli = canliOku((string) file_get_contents($dosya));
    $g = array_merge(girdiyeCevir($c), $ayarlar);
    $s = $motor->hesapla($g);
    $satirlar = [];
    $sayac = ['tutar' => [0, 0], 'sure' => [0, 0], 'destek' => [0, 0]];
    if ($s['hata'] !== null) {
        echo "!! $etiket: motor hatası — {$s['hata']}\n";
        $md[] = "## $etiket\n\nMotor hatası: {$s['hata']}\n";
        continue;
    }

    $tutarKontrol = function (string $ad, ?array $aralik, float $bizim) use (&$satirlar, &$sayac) {
        if ($aralik === null) {
            return;
        }
        $tuttu = $bizim >= $aralik['lo'] - 0.5 && $bizim < $aralik['hi'] + 0.5;
        $orta = ($aralik['lo'] + $aralik['hi']) / 2;
        $sapma = $tuttu ? 0 : ($bizim < $aralik['lo'] ? $bizim - $aralik['lo'] : $bizim - $aralik['hi']);
        $sayac['tutar'][1]++;
        $sayac['tutar'][0] += $tuttu ? 1 : 0;
        $satirlar[] = [$tuttu ? 'TUTTU' : 'TUTMADI', $ad, tlKisa($aralik['lo']) . ' – ' . tlKisa($aralik['hi']), tlKisa($bizim),
            $tuttu ? '' : (($sapma > 0 ? '+' : '') . tlKisa($sapma) . ' (%' . number_format(100 * $sapma / max(1, $orta), 1, ',', '') . ')')];
    };

    foreach (['gecmis' => 'Kazalı geliri · geçmiş', 'aktif' => 'Kazalı geliri · aktif', 'pasif' => 'Kazalı geliri · pasif'] as $k => $ad) {
        $tutarKontrol($ad, $canli['kazali'][$k] ?? null, $s['kazaliGeliri'][$k]);
    }
    $sonucMap = [];
    foreach ($s['sonuclar'] as $x) {
        $sonucMap[$x['id']] = $x;
    }
    foreach ($canli['kisi'] as $anahtar => $aralik) {
        $bizim = $sonucMap[$anahtar]['toplam'] ?? 0.0;
        if ($aralik['hi'] <= 250000 && $bizim == 0.0 && !isset($sonucMap[$anahtar])) {
            continue; // canlı boş satırlar için "0–250 bin" gösterir
        }
        $tutarKontrol('Kişi · ' . $anahtar, $aralik, $bizim);
    }
    $tutarKontrol('İşlemiş faiz', $canli['faiz'], $s['faiz']['tutar'] ?? 0.0);
    if (!$canli['kisi'] && $s['mod'] === 'isgucu') {
        $tutarKontrol('Kazalı · toplam', $canli['toplam'], $s['toplam']);
    } else {
        $tutarKontrol('Genel toplam', $canli['toplam'], $s['toplam']);
    }

    foreach ($canli['sure'] as $k => $ay) {
        $bizimAy = $s['ozet']['sureAy'][$k];
        $fark = $bizimAy - $ay;
        $sayac['sure'][1]++;
        $sayac['sure'][0] += abs($fark) <= 1 ? 1 : 0;
        $satirlar[] = [$fark === 0 ? 'TUTTU' : (abs($fark) <= 1 ? '±1 AY' : 'TUTMADI'), 'Süre · ' . $k,
            intdiv($ay, 12) . 'y ' . ($ay % 12) . 'a', intdiv($bizimAy, 12) . 'y ' . ($bizimAy % 12) . 'a', $fark ? ($fark > 0 ? '+' : '') . $fark . ' ay' : ''];
    }
    $hsMap = [];
    foreach ($s['hakSahipleri'] as $h) {
        $hsMap[$h['anahtar']] = $h;
    }
    foreach ($canli['destek'] as $anahtar => $d) {
        if ($d['yas'] === 0 && $d['yil'] === 0) {
            continue;
        }
        $bizim = $hsMap[$anahtar]['destekYili'] ?? null;
        $tuttu = $bizim === $d['yil'];
        $sayac['destek'][1]++;
        $sayac['destek'][0] += $tuttu ? 1 : 0;
        $satirlar[] = [$tuttu ? 'TUTTU' : 'TUTMADI', 'Destek yılı · ' . $anahtar, (string) $d['yil'], (string) $bizim, ''];
    }

    foreach ($sayac as $k => [$a, $b]) {
        $genel[$k][0] += $a;
        $genel[$k][1] += $b;
    }
    $ozet[] = [$etiket, $sayac];

    printf("\n== %s  (tutar %d/%d · süre %d/%d · destek %d/%d)\n", $etiket, $sayac['tutar'][0], $sayac['tutar'][1], $sayac['sure'][0],
        $sayac['sure'][1], $sayac['destek'][0], $sayac['destek'][1]);
    $md[] = "## $etiket";
    $md[] = '';
    $md[] = sprintf('Tutar **%d/%d** · Süre **%d/%d** · Destek yılı **%d/%d**', $sayac['tutar'][0], $sayac['tutar'][1], $sayac['sure'][0], $sayac['sure'][1],
        $sayac['destek'][0], $sayac['destek'][1]);
    $md[] = '';
    $md[] = '| Durum | Kalem | Canlı | Motor | Sapma |';
    $md[] = '|---|---|---|---|---|';
    foreach ($satirlar as $r) {
        if ($r[0] !== 'TUTTU' && !$ozetMod) {
            printf("   %-8s %-28s canlı %-22s motor %-12s %s\n", $r[0], $r[1], $r[2], $r[3], $r[4]);
        }
        $md[] = '| ' . implode(' | ', $r) . ' |';
    }
    if ($canli['uyari']) {
        $md[] = '';
        $md[] = 'Canlı uyarılar: ' . implode(' · ', $canli['uyari']);
    }
    $md[] = '';
}

$oran = fn (array $x) => $x[1] ? sprintf('%d/%d (%%%s)', $x[0], $x[1], number_format(100 * $x[0] / $x[1], 0)) : '-';
printf("\nGENEL  tutar %s · süre (±1 ay) %s · destek yılı %s\n", $oran($genel['tutar']), $oran($genel['sure']), $oran($genel['destek']));
if ($ozetMod) {
    exit(0);
}
array_splice($md, 6, 0, [sprintf('**Genel:** tutar %s · süre (±1 ay) %s · destek yılı %s', $oran($genel['tutar']), $oran($genel['sure']), $oran($genel['destek'])), '']);
file_put_contents(__DIR__ . '/rapor.md', implode("\n", $md) . "\n");
file_put_contents(__DIR__ . '/rapor.json', json_encode(['genel' => $genel, 'vakalar' => $ozet], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
