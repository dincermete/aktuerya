<?php

declare(strict_types=1);

namespace DestekTazminat\Hesap;

use DestekTazminat\Destek\Sayi;
use DestekTazminat\Destek\Tarih;
use DestekTazminat\Veri\AsgariUcret;
use DestekTazminat\Veri\EvlenmeIhtimali;
use DestekTazminat\Veri\Faiz;
use DestekTazminat\Veri\SgkPsd;
use DestekTazminat\Veri\YasamTablosu;

/**
 * Destekten yoksun kalma (TBK 53) ve iş gücü kaybı (TBK 54) tazminatı motoru.
 * Çerçeveden bağımsızdır: dizi girer, JSON'a çevrilebilir dizi çıkar.
 *
 * Geçmiş dönem gün bazında, gelecek dönem ay bazında hesaplanır (canlı siteyle ölçülerek doğrulandı):
 * aktif ve pasif dönem tam aylara bölünür, her 12 ay bir yıl satırıdır, n. satırın katsayısı (1+a)ⁿ ÷ (1+i)ⁿ.
 * Pasif dönemin satır numarası aktif dönemin tam yıl sayısından devam eder.
 * Kişi, kalan destek yılı satır numarasından küçük değilse o satırda pay alır.
 */
final class Hesaplayici
{
    public function hesapla(array $ham): array
    {
        try {
            return $this->calistir(Girdi::normalize($ham));
        } catch (HesapHatasi $e) {
            return ['hata' => $e->getMessage()];
        }
    }

    private function calistir(array $g): array
    {
        $destek = $g['mod'] === 'destek';
        $aktuer = $g['yontem'] === 'aktuer';
        $notlar = [];

        $O = $g['olayTarihi'];
        $R = $g['raporTarihi'];
        $KB = $g['dogumTarihi'];
        if ($O === null || $KB === null || $R === null) {
            throw new HesapHatasi('Olay tarihi, kazalı doğum tarihi ve rapor tarihi gerekli.');
        }
        if ($KB >= $O) {
            throw new HesapHatasi('Kazalının doğum tarihi olay tarihinden önce olmalı.');
        }
        if ($R < $O) {
            throw new HesapHatasi('Rapor tarihi olay tarihinden önce olamaz.');
        }

        $K = $g['kusur'] / 100;
        YasamTablosu::$pmfDuzeltilen = array_values(array_map('intval', array_filter(explode(',', (string) $g['_pmfDuzelt']), 'is_numeric')));
        $tablo = $g['tablo'];
        // "Bakiye ömürleri rapor tarihine göre bul" canlı sitede yalnızca hak sahiplerine uygulanıyor; kazalının ömrü hep olay tarihinden.
        $omurBaz = $g['bakiyeRaporTarihine'] ? $R : $O;
        $kYasO = Tarih::yas($KB, $O);
        $eK = YasamTablosu::bakiye($tablo, $g['cinsiyet'], $kYasO, $g['yasYukariYuvarla']);
        if ($g['tamHayat'] && !$destek) {
            // Tam hayat anüitesi: kazalının 99 yaşına kadar yaşayabileceği kabul edilir (49_tam_hayat_isgucu: bakiye 782 ay)
            $eK = max(0.0, 99 - ($O - $KB) / 365);
            $notlar[] = 'Tam hayat yaklaşımı nedeniyle kazalının 99 yaşına kadar yaşama ihtimali kabul edildi.';
        }
        $L = Tarih::yilEkle($O, $eK);
        if ($L <= $O) {
            throw new HesapHatasi('Seçilen tabloya göre kazalının bakiye ömrü olay tarihinde sona ermiş.');
        }

        $gelirBas = Tarih::yilEkle($KB, $g['aktifBaslamaYasi']);
        $A = min(Tarih::yilEkle($KB, $g['aktifBitisYasi']), $L);
        $G = $g['gecmisRaporTarihiIle'] ? $R : Tarih::ymd(Tarih::yil($R) + 1, 1, 1);
        $G = max($O, min($G, $L));
        $pasifBas = max($A, $G);
        $sure = Sureler::hesapla($KB, $O, $G, $A, $eK, $g['aktifBaslamaYasi'], $g['aktifBitisYasi']);
        // Tutar için aktif dönem, günleri yok sayan ay farkı; ekrandaki süre gün × 12 ÷ 365,25 ile gösterilir.
        // 15_askerlik: ekranda 445 ay, tutar 446 ay (01.01.2027 → 01.03.2064).
        // 14_gecdon_raptar: 13.09.2026 → 12.06.2049 tutarı 273 ay ölçüldü.
        $aktifAy = $A > $G ? Tarih::ayFarkiGunsuz($G, $A) : 0;
        $pasifAy = $sure['pasifDonem'];
        $aktifTamYil = intdiv($aktifAy, 12);

        $askerBas = $askerSon = null;
        if ($g['askerlikDus'] && $g['cinsiyet'] === 'E' && $kYasO < $g['askerlikYasi']) {
            $askerBas = Tarih::yilEkle($KB, $g['askerlikYasi']);
            if ($g['_askerBas'] === 'yil') {
                // Canlı site askerliği, askerlik yaşının dolduğu yılın 1 Ocak'ından başlatır (35: 22 yaş/12 ay → 2026'nın tamamı; 95: 21/12 → 2025)
                $askerBas = Tarih::ymd(Tarih::yil($askerBas), 1, 1);
            }
            // Süre: canlıda 3 ay → 95 gün, 6 ay → 185 gün, 12 ay → 365 gün düşülüyor (96, 34, 95, 35 ölçümleri) = ay × 30 + 5 gün
            $askerSon = $g['_askerBas'] === 'yil'
                ? $askerBas + (int) $g['askerlikSuresi'] * 30 + 5
                : Tarih::ayEkle($askerBas, (int) $g['askerlikSuresi']);
            // Gelecek dönemde (ay bazında) tam askerlik süresi kadar ay düşülür (97_askerlik_23_6: 6 ay)
            $askerSonAy = Tarih::ayEkle($askerBas, (int) $g['askerlikSuresi']);
            if (!$destek && $g['_askerBas'] === 'yil') {
                // İş gücü kaybında canlı site süreden bağımsız yalnızca 5 gün düşüyor, gelecek dönemde hiç düşmüyor
                // (98: 6 ay ve 129: 12 ay → geçmiş aynı, 5 gün eksik · 128: gelecekteki askerlik düşülmedi)
                $askerSon = $askerBas + 5;
                $askerSonAy = $askerBas;
            }
            $notlar[] = 'Askerlik: ' . Tarih::yaz($askerBas) . ' – ' . Tarih::yaz($askerSon) . ' arası gelir hesaplanmadı.';
        }

        // --- Hak sahipleri ve destek süreleri ---
        $kisiler = [];
        $satirlar = [];
        foreach ($g['hakSahipleri'] as $h) {
            $tur = $h['tur'];
            $etiket = $h['etiket'];
            if ($g['anneBabaYerineCocuk'] && ($tur === 'anne' || $tur === 'baba')) {
                $tur = 'cocuk';
                $etiket = $h['anahtar'] === 'ana' ? '6. Çocuk' : '7. Çocuk';
            }
            $satir = ['anahtar' => $h['anahtar'], 'etiket' => $etiket, 'yas' => null, 'destekYili' => null, 'hesaplaniyor' => false];
            if ($h['dogumTarihi'] === null) {
                $satirlar[] = $satir;
                continue;
            }
            $yasO = Tarih::yas($h['dogumTarihi'], $O);
            $tamYas = YasamTablosu::tamYas($yasO, $g['yasYukariYuvarla']);
            $satir['yas'] = $tamYas;
            $cins = match ($tur) {
                'anne' => 'K',
                'baba' => 'E',
                default => $h['cinsiyet'],
            };
            $kazaliSinirli = false;
            if ($tur === 'cocuk') {
                $sinir = $h['yuksekOgrenim'] ? $g['universiteDestekSonu'] : ($cins === 'K' ? $g['kizDestekSonu'] : $g['erkekDestekSonu']);
                $yil = max(0, (int) round($sinir) - $tamYas);
            } else {
                $e = YasamTablosu::bakiye($tablo, $cins, Tarih::yas($h['dogumTarihi'], $omurBaz), $g['yasYukariYuvarla']);
                $kendiSonu = Tarih::yilEkle($omurBaz, $e);
                // Kendi ömrüyle sınırlıysa bakiye ömür yıla yuvarlanır (21,65→22 · 28,46→28 · 27,90→28);
                // kazalının ömrüyle sınırlıysa olay → kazalı ömür sonu takvim farkı kullanılır (44,45→45).
                $kazaliSinirli = $kendiSonu > $L;
                // Kazalının ömrüyle sınırlı eş: round(e + (tavan(yaş₃₆₅) − yaş₃₆₅)); olay tarihinde emeklilik yaşını geçmiş kazalıda round(e).
                // Ölçülen: TRH 41,58→42 · 49,24→49 · 44,45→45 · CSO 40,46→41 · GATT 47,92→48 · 62 yaşında kazalı 16,14→16
                $yas365K = ($O - $KB) / 365;
                $yil = $kazaliSinirli
                    ? (int) round($yas365K < $g['aktifBitisYasi'] ? $eK + ceil($yas365K - 1e-9) - $yas365K : $eK)
                    : (int) round($e + ($omurBaz - $O) / 365.25);
                if ($tur === 'es' && $kendiSonu > $L && $h['hesapla']) {
                    $notlar[] = 'Eşin bakiye ömrü kazalınınkinden uzun; destek süresi kazalının ömür sonuna eşitlendi.';
                }
            }
            $sonHam = Tarih::yilEkle($O, $yil);
            if ($h['destekSonu'] !== null && $h['destekSonu'] < $sonHam) {
                $sonHam = max($O, $h['destekSonu']);
                $yil = (int) round(($sonHam - $O) / 365.25);
            }
            $son = min($sonHam, $L);
            $satir['destekYili'] = $yil;
            // Ana rahmindeki çocuk (olaydan en çok 300 gün sonra doğan) destek alır; daha sonra doğan alamaz.
            $olaydanSonra = $h['dogumTarihi'] > $O + 300;
            if ($olaydanSonra && $h['hesapla']) {
                $notlar[] = "{$etiket} olay tarihinden 300 günden fazla sonra doğduğu için destek hesabına katılmadı.";
            }
            if ($destek && $h['hesapla'] && $son > $O && !$olaydanSonra) {
                $satir['hesaplaniyor'] = true;
                $kisiler[] = [
                    'id' => $h['anahtar'], 'ad' => $h['ad'] !== '' ? $h['ad'] : $etiket, 'tur' => $tur, 'cinsiyet' => $cins,
                    'dogum' => $h['dogumTarihi'], 'bas' => $O, 'son' => $son, 'sonHam' => $sonHam, 'yil' => $yil, 'psd' => $h['psd'],
                    'sgkGelir' => $h['sgkGelir'],
                    'kazaliSinirli' => $kazaliSinirli && $h['destekSonu'] === null,
                ];
            }
            $satirlar[] = $satir;
        }

        // SGK aylık geliri yazılmışsa PSD tariften hesaplanır ve elle yazılan PSD'nin yerine geçer
        $gelireGiris = $g['sgkBaslangic'] ?? $O;
        if ($g['_sgkPsdOtomatik']) {
            foreach ($kisiler as &$k) {
                if ($k['sgkGelir'] <= 0) {
                    continue;
                }
                $psd = SgkPsd::hesapla($k['sgkGelir'], SgkPsd::tabloAdi($k['tur'], $k['cinsiyet'], $g['cinsiyet']), $k['dogum'], $gelireGiris);
                if ($psd !== null) {
                    $k['psd'] = $psd['psd'];
                    $notlar[] = "{$k['ad']}: SGK geliri PSD = " . Sayi::tl($k['sgkGelir']) . " × 12 × {$psd['katsayi']} ÷ 100 = " . Sayi::tl($psd['psd'])
                        . " ({$psd['surum']} {$psd['tablo']}, gelire giriş yaşı {$psd['yas']})." . ($psd['not'] ? ' ' . $psd['not'] : '');
                }
            }
            unset($k);
        }

        $sanallar = [];
        $gercekEsCocuk = array_filter($kisiler, fn ($k) => $k['tur'] === 'es' || $k['tur'] === 'cocuk');
        $ebeveynVar = (bool) array_filter($kisiler, fn ($k) => $k['tur'] === 'anne' || $k['tur'] === 'baba');
        if ($destek && $g['faraziEvlenme'] > 0 && !$gercekEsCocuk && $ebeveynVar) {
            $evYas = $g['faraziEvlenme'];
            $c1Yas = $g['farazi1Cocuk'];
            $c2Yas = $g['farazi2Cocuk'];
            if ($kYasO >= $evYas) {
                // Kazalı farazi evlenme yaşından büyükse yaş otomatik yükseltilir: tam yaş + 2, çocuk yaşları aynı farkla
                // (90_farazi_28_yasli_bekar: 33 yaş → 35 / 37 / 39)
                $yeni = floor($kYasO + 1e-6) + 2;
                $c1Yas += $yeni - $evYas;
                $c2Yas += $yeni - $evYas;
                $evYas = $yeni;
                $notlar[] = 'Destek veren farazi evlenme yaşından büyük olduğundan farazi evlenme yaşı ' . Sayi::kisa($evYas) . '\'e yükseltildi.';
            }
            $ev = Tarih::yilEkle($KB, $evYas);
            $c1 = Tarih::yilEkle($KB, $c1Yas);
            $c2 = Tarih::yilEkle($KB, $c2Yas);
            if ($ev < $L) {
                $sanallar[] = ['id' => '_es', 'tur' => 'es', 'bas' => $ev, 'son' => $L];
            }
            // Farazi çocukların üniversite bitiminde destekten çıkacağı varsayılır (canlı uyarı metni)
            $uni = $g['universiteDestekSonu'];
            if ($c1 < $L) {
                $sanallar[] = ['id' => '_c1', 'tur' => 'cocuk', 'bas' => $c1, 'son' => min($L, Tarih::yilEkle($c1, $uni))];
            }
            if ($c2 < $L) {
                $sanallar[] = ['id' => '_c2', 'tur' => 'cocuk', 'bas' => $c2, 'son' => min($L, Tarih::yilEkle($c2, $uni))];
            }
            $notlar[] = 'Bekâr destek: ' . Sayi::kisa($evYas) . ' yaşında farazi evlenme, ' . Sayi::kisa($c1Yas)
                . ' ve ' . Sayi::kisa($c2Yas) . ' yaşlarında farazi çocuk varsayıldı (çocuklar üniversite bitimine kadar).';
        }
        if ($destek && $g['paylastirma'] === 'ayim' && !array_filter($kisiler, fn ($k) => $k['tur'] === 'es')) {
            $notlar[] = 'AYİM paylaştırma eş bulunmayan dönemlerde 2/1 yöntemiyle yapıldı.';
        }
        // "Eş ve çocuk paylarını ayır": ana-baba toplam payı olay tarihindeki eş ve çocuk sayısıyla, iki ebeveyn varsayılarak
        $olaydakiler = array_filter(array_merge($kisiler, $sanallar), fn ($k) => $k['bas'] <= $O && $k['son'] > $O);
        $wAnaBaba = $g['anneBabaYarimPay'] ? 1.0 : 2.0;
        $ayirEbeveynPayi = $wAnaBaba / (2 + $wAnaBaba
            + 2 * count(array_filter($olaydakiler, fn ($k) => $k['tur'] === 'es'))
            + count(array_filter($olaydakiler, fn ($k) => $k['tur'] === 'cocuk')));
        $yeniPaylastirma = fn (array $liste) => new Paylastirma($g['paylastirma'], $g['anneBabaYarimPay'], $g['anneBaba25SinirYok'], $liste,
            (string) $g['_yuzde70'], (string) $g['_sinirFazlasi'], false, $g['esCocukAyri'], (string) $g['_ayir'], $ayirEbeveynPayi);

        // Geçmiş dönem: tarih bazlı kişiler (gün numarası). Paylar ücret döneminin ilk gününe göre verildiğinden,
        // dönem içinde başlayan farazi kişi o dönemin başından itibaren paya girer (32_farazi_25: evlenme 2026 → 2026'nın tamamı).
        $gecmisSanallar = array_map(
            fn ($k) => $k['bas'] < $G ? array_merge($k, ['bas' => max($O, AsgariUcret::donem($k['bas'])['gun'])]) : $k,
            $sanallar
        );
        $gecmisPay = $yeniPaylastirma(array_merge($kisiler, $gecmisSanallar));

        // Gelecek dönem: satır bazlı kişiler (satır numarası n; kişi n ≤ kalan yıl ise pay alır)
        $satirNo = fn (int $tarih): int => $tarih <= $G ? 1 : (int) floor(($tarih - $G) / 365.25 + 1e-9) + 1;
        // Geçmiş dönemin tükettiği destek yılı: geçmiş ay ÷ 12, yarımda aşağı yuvarlanır (3,75→4 · 3,5→3 · 7,33→7)
        $gecmisYil = (int) ceil($sure['gecmisDonem'] / 12 - 0.5);
        $gelecekKisiler = [];
        foreach ($kisiler as $k) {
            $kalan = $g['_kalan'] === 'tarih'
                ? (int) floor(($k['sonHam'] - $G) / 365.25 + 1e-9)
                : $k['yil'] - $gecmisYil;
            if ($g['_esSinirsiz'] && $k['kazaliSinirli']) {
                $kalan = 9999; // kazalının ömrüyle sınırlı kişi ömür sonuna kadar pay alır
            }
            $gelecekKisiler[] = ['id' => $k['id'], 'tur' => $k['tur'], 'bas' => 1, 'son' => max(1, $kalan + 1)];
        }
        foreach ($sanallar as $k) {
            $gelecekKisiler[] = ['id' => $k['id'], 'tur' => $k['tur'], 'bas' => $satirNo($k['bas']),
                'son' => (int) floor(($k['son'] - $G) / 365.25 + 1e-9) + 1];
        }
        $gelecekPay = $yeniPaylastirma($gelecekKisiler);

        // --- Gelir ---
        $agi = fn (int $d): bool => !$g['agisiz'] && AsgariUcret::agiUygulanir($d);
        // "AGİ'siz" seçiliyken geçmiş dönemde yalnızca AGİ çıkarılır (2022 sonrası normal net), gelecek dönemde vergi kesilmiş net kullanılır
        // (29_agisiz_eski: geçmiş 1.132.187 TL). "Gelir vergisiz hesapla" da seçiliyse geçmiş dönemde de vergi kesilmiş net
        // (39_gv_istisna_agisiz: geçmiş 973.451 TL).
        $taban = function (int $d, bool $gelecek = false) use ($agi, $g): float {
            $w = AsgariUcret::donem($d);
            $net = $g['agisiz'] && ($gelecek || $g['gelirVergisiz']) ? AsgariUcret::agisizNet($d) : $w['net'];

            return $net + ($agi($d) ? $w['brut'] * 0.075 : 0.0);
        };
        $ilaveAgi = function (int $d, bool $gelecek) use ($g, $agi): float {
            if (!$g['ilaveAgi'] || !$agi($d)) {
                return 0.0;
            }
            $yuzde = 0.0;
            $sayac = 0;
            foreach ($g['hakSahipleri'] as $h) {
                if ($h['dogumTarihi'] === null || $h['dogumTarihi'] > $d) {
                    continue;
                }
                if ($h['tur'] === 'es') {
                    $yuzde += $g['esCalisiyor'] ? 0 : 10;
                } elseif ($h['tur'] === 'cocuk' && !$gelecek) {
                    $sinir = $h['yuksekOgrenim'] ? $g['universiteDestekSonu'] : ($h['cinsiyet'] === 'K' ? $g['kizDestekSonu'] : $g['erkekDestekSonu']);
                    if (Tarih::yas($h['dogumTarihi'], $d) < $sinir) {
                        $sayac++;
                        $yuzde += $sayac <= 2 ? 7.5 : 5;
                    }
                }
            }

            return AsgariUcret::donem($d)['brut'] * 0.15 * min($yuzde, 35) / 100;
        };

        // Canlı site ücret katsayısını 2 haneye yuvarlıyor (11_yuksek_ucret: 45.000 ÷ 8.506,80 = 5,2899 → 5,29)
        $katsayi = $g['ucret'] > 0 ? round($g['ucret'] / $taban($O), 2) : 1.0;
        $notlar[] = $g['ucret'] > 0
            ? 'Ücret katsayısı ' . Sayi::kisa(round($katsayi, 4)) . ' (olay tarihi net asgari ücret ' . Sayi::tl($taban($O)) . ').'
            : 'Ücret girilmediği için asgari ücret esas alındı.';
        $sonBilinen = max($O, $G - 1);
        $aktifAylik = $aktuer
            ? $taban($R, true) * $katsayi + $ilaveAgi($R, true)
            : $taban($sonBilinen, true) * $katsayi + $ilaveAgi($sonBilinen, true);
        $pasifAylik = max($g['pasifGelir'], AsgariUcret::agisizNet($sonBilinen));
        if ($aktuer) {
            $pasifAylik = max($pasifAylik, 0.7 * $aktifAylik);
        }
        $artis = $g['artis'] / 100;
        $iskonto = $g['iskonto'] / 100;
        $carpan = (1 + $artis) / (1 + $iskonto);
        $oran = $destek ? 1.0 : $g['isgucuOrani'] / 100;

        // --- Evlenme ihtimali ---
        $evlenme = null;
        foreach ($kisiler as $k) {
            if ($k['tur'] === 'es') {
                $ref = $g['evlenmeOlayTarihine'] ? $O : $R;
                $yas = (int) floor(Tarih::yas($k['dogum'], $ref) + 1e-6);
                // Çocuk sayısı olay tarihinde destek alanlara göre (28_67cocuk: rapor tarihinde 18'i geçen çocuk da sayılıyor)
                $cocuk = count(array_filter($kisiler, fn ($x) => $x['tur'] === 'cocuk' && $x['son'] > $O));
                $evlenme = ['yas' => $yas, 'tablo' => EvlenmeIhtimali::oran($yas, $k['cinsiyet']), 'cocuk' => $cocuk,
                    'oran' => EvlenmeIhtimali::indirimli($yas, $k['cinsiyet'], $cocuk)];
                $notlar[] = "Dul eşin evlenme ihtimali: AYİM %{$evlenme['tablo']} ({$yas} yaş) − {$cocuk} çocuk × 5 = %{$evlenme['oran']}. Yalnızca gelecek dönem payından düşüldü.";
            }
        }

        $kazali = ['id' => 'kazali', 'cinsiyet' => $g['cinsiyet'], 'dogum' => $KB];
        $hayattaKalma = [];
        $teknikFaiz = $g['teknikFaiz'] / 100;
        $teknikUs = (string) $g['_teknikUs'];
        // Aktüeryal çarpan: hayatta kalma olasılığı l(y+n−1) ÷ l(y), teknik faiz varsa (1 + t)^−(n−1) ile birlikte
        $aktuerYas = (string) $g['_aktuerYas'];
        $aktuerKayma = (int) ($destek ? $g['_aktuerKayma'] : $g['_aktuerKaymaIsgucu']);
        $turetilmisLx = $g['_lx'] === 'turetilmis';
        // Pasif satırlarda hayatta kalma adımı: aktif dönemin kısmi satırı varsa satır sayısı bir fazla sayılır
        $pasifKayma = match ((string) $g['_aktuerPasifKayma']) {
            '1' => 1,
            'kismi' => $aktifAy % 12 > 0 ? 1 : 0,
            default => 0,
        };
        $olasilik = function (array $k, int $n, bool $pasif = false) use (&$hayattaKalma, $tablo, $G, $O, $R, $teknikFaiz, $teknikUs,$aktuerYas, $aktuerKayma, $turetilmisLx, $pasifKayma): float {
            $anahtar = $k['id'] . '|' . $n . '|' . ($pasif ? 'p' : 'a');
            if (!isset($hayattaKalma[$anahtar])) {
                $y = match ($aktuerYas) {
                    'Gtam' => floor(Tarih::yas($k['dogum'], $G) + 1e-6),
                    'Gyuv' => round(Tarih::yas($k['dogum'], $G)),
                    'Gtavan' => ceil(Tarih::yas($k['dogum'], $G) - 1e-6),
                    'O' => Tarih::yas($k['dogum'], $O),
                    'Otam' => floor(Tarih::yas($k['dogum'], $O) + 1e-6),
                    'R' => Tarih::yas($k['dogum'], $R),
                    'Rtam' => floor(Tarih::yas($k['dogum'], $R) + 1e-6),
                    'Ryuv' => round(Tarih::yas($k['dogum'], $R)),
                    default => Tarih::yas($k['dogum'], $G),
                };
                $l0 = YasamTablosu::yasayan($tablo, $k['cinsiyet'], $y, $turetilmisLx);
                $adim = $n - 1 + $aktuerKayma + ($pasif ? $pasifKayma : 0);
                $hayattaKalma[$anahtar] = $l0 > 0
                    ? YasamTablosu::yasayan($tablo, $k['cinsiyet'], $y + $adim, $turetilmisLx) / $l0 / (1 + $teknikFaiz) ** ($teknikUs === 'adim' ? $adim : $n - 1)
                    : 0.0;
            }

            return $hayattaKalma[$anahtar];
        };

        $birikim = [];
        foreach ($kisiler as $k) {
            $birikim[$k['id']] = ['g' => 0.0, 'a' => 0.0, 'p' => 0.0];
        }
        $kazaliBirikim = ['g' => 0.0, 'a' => 0.0, 'p' => 0.0];
        $satirG = $satirA = $satirP = [];

        /** Bir tutarı kişilere (veya iş gücü kaybında kazalıya) dağıtıp satıra ve birikime yazar. */
        $dagit = function (array &$satir, string $donem, float $tutar, array $paylar, ?int $n) use (
            $destek, $aktuer, $kisiler, $kazali, $olasilik, $oran, $g, &$birikim, &$kazaliBirikim
        ): void {
            if ($destek) {
                foreach ($kisiler as $k) {
                    if (!isset($paylar[$k['id']])) {
                        continue;
                    }
                    $p = $paylar[$k['id']];
                    $v = $tutar * $p['oran'];
                    $etiket = $p['etiket'];
                    if ($aktuer && $n !== null) {
                        $ol = $olasilik($k, $n, $donem === 'p');
                        $v *= $ol;
                        $etiket .= ' × %' . Sayi::kisa(round($ol * 100, 2));
                    }
                    $birikim[$k['id']][$donem] += $v;
                    $satir['kisiler'][$k['id']] ??= ['tutar' => 0.0, 'etiket' => $etiket];
                    $satir['kisiler'][$k['id']]['tutar'] += $v;
                }
            } else {
                $olasilikUygula = $aktuer && $n !== null && ($donem === 'p' || $g['_aktuerIsgucuDonem'] !== 'pasif');
                $v = $tutar * $oran * ($olasilikUygula ? $olasilik($kazali, $n, $donem === 'p') : 1.0);
                $kazaliBirikim[$donem] += $v;
                $satir['kisiler']['kazali'] ??= ['tutar' => 0.0, 'etiket' => '%' . Sayi::kisa($g['isgucuOrani'])];
                $satir['kisiler']['kazali']['tutar'] += $v;
            }
        };

        // --- Geçmiş dönem: gün bazında ---
        // 18 yaşın tamamlandığı ücret döneminin tamamı gelire dahil edilir (76_yetistirme_yok ölçümü)
        $gelirBasGecmis = $gelirBas < $G ? min($gelirBas, AsgariUcret::donem($gelirBas)['gun']) : $gelirBas;
        // Yetiştirme gideri: kazalının gelir elde etmediği (18 yaşından önceki) günlerde ana-babanın yapacağı bakım masrafı.
        // Aylık tutar (esas gelir girilmemişse o dönemin asgari ücreti) × oran; ana-babanın tazminatından kusur indiriminden sonra düşülür.
        // Geçmişte her ücret döneminde tam ay × aylık + kalan gün × aylık ÷ 30 (87/88/89 ölçümü: %100 oranda 567.935 TL, formül 568.040 TL).
        // Geçici iş göremezlik süresinde sürekli iş gücü kaybı geliri hesaplanmaz; o günler GİG satırında %100 gelirle ödenir.
        // Canlıda sürekli kayıptan bir gün fazlası çıkarılıyor (118: 90 gün GİG → geçmiş dönem 91 günlük gelir × oran eksik).
        $gigSon = !$destek && $g['geciciHesapla'] && $g['geciciGun'] > 0
            ? $O + (int) $g['geciciGun'] + ($g['_gigHaric'] === 'gun+1' ? 1 : 0)
            : null;
        $yetistirmeOran = $destek && $g['yetistirme'] ? $g['yetistirmeOrani'] / 100 : 0.0;
        $yetistirmeTutar = 0.0;
        $yetistirmeDonem = [];
        // Esas gelir girilmişse ücret gibi olay tarihindeki asgari ücrete oranlanır ve her dönemin asgari ücretiyle ilerletilir (105_yetistirme_gelir)
        $yetistirmeKatsayi = $g['yetistirmeGelir'] > 0 ? round($g['yetistirmeGelir'] / $taban($O), 2) : 1.0;
        for ($d = $O; $d < $G; $d++) {
            $w = AsgariUcret::donem($d);
            if ($yetistirmeOran > 0 && $d < $gelirBasGecmis) {
                $yetistirmeDonem[$w['i']] ??= ['bas' => $d, 'aylik' => $taban($d) * $yetistirmeKatsayi];
                $yetistirmeDonem[$w['i']]['bitis'] = $d + 1;
            }
            $calisiyor = $d >= $gelirBasGecmis && !($askerBas !== null && $d >= $askerBas && $d < $askerSon)
                && !($gigSon !== null && $d < $gigSon);
            if ($d >= $A) {
                // Olay tarihinde emeklilik yaşını geçmiş kazalı: geçmiş dönemde de o dönemin asgari ücreti esas alınır
                $gunluk = $taban($d) / 30;
            } else {
                // Aktüeryalde geçmiş dönem rapor tarihindeki ücretle; "geçmiş dönem ücretlerine göre" seçiliyse o dönemin ücretiyle
                $gunluk = $calisiyor
                    ? (($aktuer && !$g['gecmisGercekUcret']) ? $aktifAylik : $taban($d) * $katsayi + $ilaveAgi($d, false)) / 30
                    : 0.0;
            }
            // Geçmiş dönem satırı = asgari ücret dönemi. Paylar satırın ilk gününe göre belirlenir:
            // dönem içinde destekten çıkan kişi o dönemin sonuna kadar pay alır (28_67cocuk ölçümü).
            $anahtar = 'w' . $w['i'] . ($d >= $A ? 'p' : '');
            $satirG[$anahtar] ??= ['bas' => $d, 'son' => $d, 'gun' => 0, 'gunluk' => $gunluk, 'gelir' => 0.0, 'kisiler' => []];
            $satirG[$anahtar]['son'] = $d;
            $satirG[$anahtar]['gun']++;
            $satirG[$anahtar]['gelir'] += $gunluk;
            if ($gunluk > 0 || !$satirG[$anahtar]['kisiler']) {
                $dagit($satirG[$anahtar], 'g', $gunluk, $destek ? $gecmisPay->paylar($satirG[$anahtar]['bas']) : [], null);
            }
        }

        foreach ($yetistirmeDonem as $y) {
            $tamAy = Tarih::ayFarki($y['bas'], $y['bitis']);
            $yetistirmeTutar += ($y['aylik'] * $tamAy + $y['aylik'] / 30 * ($y['bitis'] - Tarih::ayEkle($y['bas'], $tamAy))) * $yetistirmeOran;
        }

        // "Geçmiş dönem hesabı aylık maaş bazında": her ücret döneminde tam ay × aylık + kalan gün × aylık ÷ 30
        // (37_aylik_baz: 904.985 TL ölçüldü, formül 904.942 TL)
        if ($g['aylikBaz']) {
            foreach ($satirG as &$s) {
                if ($s['gelir'] <= 0 || $s['gun'] <= 0) {
                    continue;
                }
                $bitis = $s['son'] + 1;
                $tamAy = Tarih::ayFarki($s['bas'], $bitis);
                $kalanGun = $bitis - Tarih::ayEkle($s['bas'], $tamAy);
                $aylik = $s['gelir'] / $s['gun'] * 30;
                $oranAylik = ($aylik * $tamAy + $aylik / 30 * $kalanGun) / $s['gelir'];
                foreach ($s['kisiler'] as $id => &$x) {
                    $fark = $x['tutar'] * ($oranAylik - 1);
                    $x['tutar'] += $fark;
                    if ($id === 'kazali') {
                        $kazaliBirikim['g'] += $fark;
                    } else {
                        $birikim[$id]['g'] += $fark;
                    }
                }
                unset($x);
                $s['gelir'] *= $oranAylik;
            }
            unset($s);
        }

        // --- Gelecek dönem: ay bazında, 12 ay = 1 yıl satırı ---
        foreach ([['a', $G, $aktifAy, 0, $aktifAylik], ['p', $pasifBas, $pasifAy, $aktifTamYil, $pasifAylik]] as [$donem, $bas, $aySayisi, $ofset, $aylik]) {
            for ($j = 0; $j < $aySayisi; $j++) {
                $n = $ofset + intdiv($j, 12) + 1;
                $ayBas = Tarih::ayEkle($bas, $j);
                // 18 yaşın dolduğu yıl satırının tamamı gelire dahil edilir (106_yetistirme_genc: 01.06.2032 → 2032 satırı tam)
                $resit = $gelirBas < Tarih::ayEkle($bas, 12 * (intdiv($j, 12) + 1));
                $calisiyor = $resit && !($askerBas !== null && $ayBas >= $askerBas && $ayBas < $askerSonAy)
                    && !($gigSon !== null && $ayBas < $gigSon);
                $tutar = ($donem === 'p' || $calisiyor) ? $aylik * $carpan ** $n : 0.0;
                if ($yetistirmeOran > 0 && $donem === 'a' && !$resit) {
                    $yetistirmeTutar += ($katsayi > 0 ? $aylik / $katsayi : $aylik) * $yetistirmeKatsayi * $carpan ** $n * $yetistirmeOran;
                }
                if ($donem === 'a') {
                    $tab = &$satirA;
                } else {
                    $tab = &$satirP;
                }
                $tab[$n] ??= ['n' => $n, 'bas' => $ayBas, 'son' => $ayBas, 'ay' => 0, 'artisliYillik' => $aylik * 12 * (1 + $artis) ** $n,
                    'kn' => 1 / (1 + $iskonto) ** $n, 'gelir' => 0.0, 'kisiler' => []];
                $tab[$n]['son'] = Tarih::ayEkle($bas, $j + 1) - 1;
                $tab[$n]['ay']++;
                $tab[$n]['gelir'] += $tutar;
                // Kısmi (12 aydan kısa) son satır destek yılı tüketmez: kişi n − 1 ≤ kalan ise o satırda da pay alır.
                $tamSatir = intdiv($j, 12) < intdiv($aySayisi, 12);
                // Kalan yıl karşılaştırmasında pasif satır numarası: tam = aktif tam yıl + k · tavan = aktif satır sayısı (kısmi dahil) + k
                $kalanN = $n + ($donem === 'p' && $g['_pasifSatirKalan'] === 'tavan' && $aktifAy % 12 > 0 ? 1 : 0);
                $payN = $tamSatir || $g['_kismiSatir'] === 'n' ? $kalanN : max(1, $kalanN - 1);
                $dagit($tab[$n], $donem, $tutar, $destek ? $gelecekPay->paylar($payN) : [], $n);
                unset($tab);
            }
        }

        // --- Sonuçlar ---
        // Trafik kazasında PSD alanı sigorta şirketinin ödemesidir: kusurla çarpılmaz, olay tarihinden rapor tarihine yasal faiziyle düşülür
        $faizVeriSonu = $g['_faizVeriSonu'] !== null ? Tarih::gun($g['_faizVeriSonu']) : null;
        $trafikCarpani = 1 + Faiz::hesapla(1.0, $O, $R, 'yasal', $faizVeriSonu)['carpan'];
        $sonuclar = [];
        if ($destek) {
            // Yetiştirme gideri ana-baba arasında eşit bölünür; "anne çalışmıyor" seçiliyse yalnızca babadan düşülür.
            $yetistirenler = array_values(array_filter($kisiler, fn ($k) => $k['tur'] === 'baba' || ($k['tur'] === 'anne' && !$g['yetistirmeAnneYok'])));
            if ($yetistirmeTutar > 0 && $yetistirenler) {
                $notlar[] = 'Yetiştirme gideri: ' . Sayi::tl($yetistirmeTutar) . ' (%' . Sayi::kisa($g['yetistirmeOrani']) . '), '
                    . implode(' ve ', array_map(fn ($k) => $k['ad'], $yetistirenler)) . ' tazminatından kusur indirimi sonrası düşüldü.';
            }
            foreach ($kisiler as $k) {
                $b = $birikim[$k['id']];
                $yetistirme = in_array($k, $yetistirenler, true) ? $yetistirmeTutar / count($yetistirenler) : 0.0;
                $gecmis = $b['g'] * $K;
                $aktif = $b['a'] * $K;
                $pasif = $b['p'] * $K;
                $evl = $k['tur'] === 'es' && $evlenme ? ($aktif + $pasif) * $evlenme['oran'] / 100 : 0.0;
                $psd = $g['kazaTuru'] === 'is' ? $k['psd'] * $K : $k['psd'] * $trafikCarpani;
                $sonuclar[] = ['id' => $k['id'], 'ad' => $k['ad'], 'tur' => $k['tur'], 'destekSuresi' => $k['yil'],
                    'gecmis' => $gecmis, 'aktif' => $aktif, 'pasif' => $pasif, 'evlenmeIndirimi' => $evl, 'psd' => $psd,
                    'geciciIsgoremezlik' => 0.0, 'bakici' => 0.0, 'yetistirme' => $yetistirme,
                    'zarar' => $gecmis + $aktif + $pasif, 'toplam' => max(0.0, $gecmis + $aktif + $pasif - $evl - $psd - $yetistirme)];
            }
        } else {
            $gecmis = $kazaliBirikim['g'] * $K;
            $aktif = $kazaliBirikim['a'] * $K;
            $pasif = $kazaliBirikim['p'] * $K;
            $kazaliPsd = $g['kazaliPsd'];
            if ($g['_sgkPsdOtomatik'] && $g['kazaliSgkGelir'] > 0) {
                $sonuc = SgkPsd::hesapla($g['kazaliSgkGelir'], SgkPsd::tabloAdi('kazali', $g['cinsiyet'], $g['cinsiyet']), $KB,
                    $g['kazaliSgkBaslangic'] ?? $O);
                if ($sonuc !== null) {
                    $kazaliPsd = $sonuc['psd'];
                    $notlar[] = 'Kazalı SGK geliri PSD = ' . Sayi::tl($g['kazaliSgkGelir']) . " × 12 × {$sonuc['katsayi']} ÷ 100 = " . Sayi::tl($sonuc['psd'])
                        . " ({$sonuc['surum']} {$sonuc['tablo']}, gelire giriş yaşı {$sonuc['yas']}).";
                }
            }
            $psd = $g['kazaTuru'] === 'is' ? $kazaliPsd * $K : $kazaliPsd * $trafikCarpani;
            // GİG satırı: süredeki her günün geliri (dönemin aylık ücreti ÷ 30), GİÖ düşülür, kusurla çarpılır (121/122 ölçümü)
            $gecici = 0.0;
            if ($g['geciciHesapla'] && $g['geciciGun'] > 0) {
                $gigGun = (int) $g['geciciGun'] + ($g['_gigGun'] === 'gun+1' ? 1 : 0);
                for ($d = $O; $d < $O + $gigGun; $d++) {
                    $gecici += ($d >= $A ? $taban($d) : $taban($d) * $katsayi + $ilaveAgi($d, false)) / 30;
                }
                $gecici = max(0.0, $gecici - $g['gio']) * $K;
            }
            $bakici = 0.0;
            // Bakıcı oranı boşsa:
            //  - poliçe 01.04.2020 ve sonrası (ZMSS yeni genel şartları): engel %50 altı yok · %50+ kısmi bağımlı %50 · %100 tam bağımlı %100
            //    (103_isgucu_trafik_police_bakici: oran %85, poliçe 2021 → %50)
            //  - diğer: 2015 tebliği, <%70 yok · %70–79 %50 · %80–89 %75 · %90+ %100 (91_bakici_oransiz_85 → %75)
            $yeniGenelSartlar = $g['policeTarihi'] !== null && $g['policeTarihi'] >= Tarih::ymd(2020, 4, 1);
            $bakiciOrani = $g['bakiciOrani'] > 0 ? $g['bakiciOrani'] : ($yeniGenelSartlar
                ? match (true) {
                    $g['isgucuOrani'] >= 100 => 100.0,
                    $g['isgucuOrani'] >= 50 => 50.0,
                    default => 0.0,
                }
                : match (true) {
                    $g['isgucuOrani'] >= 90 => 100.0,
                    $g['isgucuOrani'] >= 80 => 75.0,
                    $g['isgucuOrani'] >= 70 => 50.0,
                    default => 0.0,
                });
            if ($g['bakiciHesapla'] && $bakiciOrani > 0) {
                $ucret = fn (int $d) => $g['bakiciNet'] ? AsgariUcret::donem($d)['net'] : AsgariUcret::donem($d)['brut'];
                // Geçmişte her ücret döneminde tam ay × aylık + kalan gün × aylık ÷ 30 (56_bakici ölçümü; gün bazında %0,11 fazla çıkıyordu)
                $aylikBazli = function (int $bas, int $bitis) use ($ucret): float {
                    $toplam = 0.0;
                    while ($bas < $bitis) {
                        $w = AsgariUcret::donem($bas);
                        $donemSonu = $bitis;
                        for ($d = $bas; $d < $bitis; $d++) {
                            if (AsgariUcret::donem($d)['i'] !== $w['i']) {
                                $donemSonu = $d;
                                break;
                            }
                        }
                        $tamAy = Tarih::ayFarki($bas, $donemSonu);
                        $toplam += $ucret($bas) * $tamAy + $ucret($bas) / 30 * ($donemSonu - Tarih::ayEkle($bas, $tamAy));
                        $bas = $donemSonu;
                    }

                    return $toplam;
                };
                if ($g['bakiciGun'] > 0) {
                    $bakici += $aylikBazli($O, min($L, $O + (int) $g['bakiciGun']));
                } else {
                    $bakici += $aylikBazli($O, $G);
                    for ($j = 0; $j < $aktifAy + $pasifAy; $j++) {
                        $nn = $j < $aktifAy ? intdiv($j, 12) + 1 : $aktifTamYil + intdiv($j - $aktifAy, 12) + 1;
                        $bakici += $ucret($sonBilinen) * $carpan ** $nn * ($aktuer ? $olasilik($kazali, $nn) : 1.0);
                    }
                }
                $bakici *= $bakiciOrani / 100 * $K;
            }
            $sonuclar[] = ['id' => 'kazali', 'ad' => $g['kazaliAdi'] !== '' ? $g['kazaliAdi'] : 'Kazalı', 'tur' => 'kazali',
                'destekSuresi' => round(($L - $O) / 365.25, 2), 'gecmis' => $gecmis, 'aktif' => $aktif, 'pasif' => $pasif, 'evlenmeIndirimi' => 0.0,
                'psd' => $psd, 'geciciIsgoremezlik' => $gecici, 'bakici' => $bakici,
                'zarar' => $gecmis + $aktif + $pasif + $gecici + $bakici,
                'toplam' => max(0.0, $gecmis + $aktif + $pasif + $gecici + $bakici - $psd)];
        }

        if ($destek) {
            $kazaliGeliri = ['gecmis' => 0.0, 'aktif' => 0.0, 'pasif' => 0.0];
            foreach (['gecmis' => $satirG, 'aktif' => $satirA, 'pasif' => $satirP] as $ad => $liste) {
                foreach ($liste as $s) {
                    $kazaliGeliri[$ad] += $s['gelir'] * $K;
                }
            }
        } else {
            // İş gücü kaybında oran ve (aktüeryalde) hayatta kalma olasılığı dahil
            $kazaliGeliri = ['gecmis' => $kazaliBirikim['g'] * $K, 'aktif' => $kazaliBirikim['a'] * $K, 'pasif' => $kazaliBirikim['p'] * $K];
        }

        // --- Paylaşım dönemleri: aynı paylara sahip ardışık satırlar birleştirilir ---
        $paylasim = [];
        if ($destek && $kisiler) {
            $onceki = null;
            foreach (array_merge(array_values($satirG), array_values($satirA), array_values($satirP)) as $s) {
                $etiketler = [];
                foreach ($kisiler as $k) {
                    if (isset($s['kisiler'][$k['id']]) && $s['kisiler'][$k['id']]['tutar'] > 0) {
                        $etiketler[$k['id']] = preg_replace('/ × %.*$/u', '', $s['kisiler'][$k['id']]['etiket']);
                    }
                }
                if (!$etiketler) {
                    continue;
                }
                $sureYil = isset($s['gun']) ? $s['gun'] / 365.25 : $s['ay'] / 12;
                if ($onceki !== null && $paylasim[$onceki]['paylar'] === $etiketler) {
                    $paylasim[$onceki]['son'] = Tarih::yaz($s['son']);
                    $paylasim[$onceki]['sure'] += $sureYil;
                } else {
                    $paylasim[] = ['bas' => Tarih::yaz($s['bas']), 'son' => Tarih::yaz($s['son']), 'sure' => $sureYil, 'paylar' => $etiketler];
                    $onceki = count($paylasim) - 1;
                }
            }
        }

        $yuvarla = function (array $liste) use ($KB): array {
            $out = [];
            foreach ($liste as $s) {
                $s['yas'] = round(Tarih::yas($KB, $s['son'] + 1), 1);
                $s['bas'] = Tarih::yaz($s['bas']);
                $s['son'] = Tarih::yaz($s['son']);
                $out[] = $s;
            }

            return $out;
        };

        // İşlemiş faiz: toplam tazminat üzerinden, faiz başlangıcından (olaydan önceyse olay tarihinden) rapor tarihine
        $toplamTazminat = array_sum(array_column($sonuclar, 'toplam'));
        $faizBas = max($O, $g['faizBaslangic'] ?? $O);
        $faiz = Faiz::hesapla($toplamTazminat, $faizBas, $R, $g['faizTuru'] === 'avans' ? (string) $g['_avansSerisi'] : 'yasal', $faizVeriSonu);

        return [
            'hata' => null,
            'mod' => $g['mod'],
            'faiz' => ['tutar' => $faiz['tutar'], 'tur' => $g['faizTuru'], 'bas' => Tarih::yaz($faizBas), 'son' => Tarih::yaz($R),
                'carpan' => $faiz['carpan'], 'dilimler' => $faiz['dilimler']],
            'ozet' => [
                'olayYasi' => $kYasO,
                'dogumTarihi' => Tarih::yaz($KB),
                'bakiyeOmur' => $eK,
                'omurSonu' => Tarih::yaz($L),
                'faalCalisma' => $sure['faalCalisma'] / 12,
                'gecmisDonem' => ($G - $O) / 365.25,
                'gecmisBas' => Tarih::yaz($O),
                'gecmisSon' => Tarih::yaz($G - 1),
                'aktifDonem' => $aktifAy / 12,
                'aktifSon' => Tarih::yaz($aktifAy ? Tarih::ayEkle($G, $aktifAy) - 1 : $G - 1),
                'pasifDonem' => $pasifAy / 12,
                'pasifSon' => Tarih::yaz($L - 1),
                'raporTarihi' => Tarih::yaz($R),
                'sureAy' => $sure,
            ],
            'parametre' => [
                'kusur' => $g['kusur'], 'yontem' => $g['yontem'], 'tablo' => $g['tablo'], 'paylastirma' => $g['paylastirma'],
                'aktifAylik' => $aktifAylik, 'pasifAylik' => $pasifAylik, 'artis' => $g['artis'], 'iskonto' => $g['iskonto'],
                'ucretKatsayisi' => $katsayi, 'isgucuOrani' => $g['isgucuOrani'],
            ],
            'kazaliGeliri' => $kazaliGeliri,
            'hakSahipleri' => $satirlar,
            'kolonlar' => $destek ? array_map(fn ($k) => ['id' => $k['id'], 'ad' => $k['ad'], 'tur' => $k['tur']], $kisiler)
                : [['id' => 'kazali', 'ad' => $g['kazaliAdi'] !== '' ? $g['kazaliAdi'] : 'Kazalı', 'tur' => 'kazali']],
            'sonuclar' => $sonuclar,
            'toplam' => array_sum(array_column($sonuclar, 'toplam')),
            'evlenme' => $evlenme,
            'paylasim' => $paylasim,
            'tablolar' => ['gecmis' => $yuvarla($satirG), 'aktif' => $yuvarla($satirA), 'pasif' => $yuvarla($satirP)],
            'notlar' => $notlar,
        ];
    }
}
