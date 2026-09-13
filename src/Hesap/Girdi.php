<?php

declare(strict_types=1);

namespace DestekTazminat\Hesap;

use DestekTazminat\Destek\Sayi;
use DestekTazminat\Destek\Tarih;

/**
 * Ham girdiyi (form, JSON, CLI) motorun beklediği tiplere çevirir ve varsayılanları uygular.
 * Tarihler "Y-m-d" veya "d.m.Y"; tutarlar "45.000,00" veya sayı olabilir.
 */
final class Girdi
{
    public const HAK_SAHIPLERI = [
        'es' => ['etiket' => 'Eş', 'tur' => 'es'],
        'c1' => ['etiket' => '1. Çocuk', 'tur' => 'cocuk'],
        'c2' => ['etiket' => '2. Çocuk', 'tur' => 'cocuk'],
        'c3' => ['etiket' => '3. Çocuk', 'tur' => 'cocuk'],
        'c4' => ['etiket' => '4. Çocuk', 'tur' => 'cocuk'],
        'c5' => ['etiket' => '5. Çocuk', 'tur' => 'cocuk'],
        'ana' => ['etiket' => 'Anne', 'tur' => 'anne'],
        'baba' => ['etiket' => 'Baba', 'tur' => 'baba'],
    ];

    public const VARSAYILAN = [
        'mod' => 'destek',            // destek | isgucu
        'kazaTuru' => 'is',           // is | trafik
        'yontem' => 'progresif',      // progresif | aktuer
        'tablo' => 'trh',             // trh | pmf
        'paylastirma' => '21',        // 21 | ayim | 70
        'kazaliAdi' => '',
        'kusur' => 100,
        'olayTarihi' => null,
        'dogumTarihi' => null,
        'cinsiyet' => 'E',
        'raporTarihi' => null,
        'ucret' => 0,
        'ilaveAgi' => false,
        'teknikFaiz' => 0,            // aktüeryal yöntemde yıllık teknik faiz %
        'isgucuOrani' => 100,
        'kazaliPsd' => 0,
        'geciciGun' => 0,
        'gio' => 0,
        'bakiciOrani' => 0,
        'bakiciGun' => 0,
        'bakiciNet' => true,
        'artis' => 10,
        'iskonto' => 10,
        'aktifBaslamaYasi' => 18,
        'aktifBitisYasi' => 60,
        'pasifGelir' => 28075.50,
        'erkekDestekSonu' => 18,
        'kizDestekSonu' => 22,
        'universiteDestekSonu' => 25,
        'faraziEvlenme' => null,      // boş: erkek 28 / 30 / 32, kadın 25 / 27 / 29 (canlı sitenin varsayılanı) · 0 = farazi evlenme uygulanmaz
        'farazi1Cocuk' => null,
        'farazi2Cocuk' => null,
        'aylikBaz' => false,          // geçmiş dönem ve geçici iş göremezlik gün yerine ay bazında
        'gelirVergisiz' => false,     // AGİ'siz hesaplarda 2022 sonrası gelir vergisi istisnası düşülür
        'gecmisGercekUcret' => false, // aktüeryalde geçmiş dönem o dönemin ücretleriyle
        'esCocukAyri' => false,       // eş ve çocuk paylarını anne-babadan ayır
        'tamHayat' => false,          // iş gücü kaybında dönem başı ödemeli tam hayat anüitesi (99 yaş)
        'geciciHesapla' => false,
        'bakiciHesapla' => false,
        'yetistirme' => false,
        'yetistirmeAnneYok' => false,
        'yetistirmeOrani' => 0,
        'yetistirmeGelir' => 0,
        'faizBaslangic' => null,
        'faizTuru' => 'yasal',        // yasal | avans
        'policeTarihi' => null,
        'kazaliSgkGelir' => 0,
        'kazaliSgkBaslangic' => null,
        'sgkBaslangic' => null,
        'askerlikDus' => true,        // canlı sitede varsayılan açık; "Asker Değil" işaretlenince kapanır (15_askerlik ölçümü)
        'askerlikYasi' => 21,
        'askerlikSuresi' => 6,
        'gecmisRaporTarihiIle' => false,
        'agisiz' => false,
        'esCalisiyor' => false,
        'bakiyeRaporTarihine' => false,
        'yasYukariYuvarla' => false,
        'evlenmeOlayTarihine' => false,
        'anneBabaYarimPay' => false,
        'anneBaba25SinirYok' => false,
        'anneBabaYerineCocuk' => false,
        // Kalibrasyon düğmeleri (arayüzde yok). Varsayılanlar canlı siteyle karşılaştırmada en çok tutan varyantlardır.
        '_yuzde70' => 'd',            // d: ana-baba toplam %25, eş+çocuk kalan %75'e orantılı (canlıda ölçülen) · a: ana-baba artandan · b: herkes %100'e orantılı · c: ana-baba %25'er
        '_ayir' => 'olay',            // eş/çocuk paylarını ayır: olay (ana-baba payı olay tarihindeki kişilerden sabit) · sinir (satırdaki eş hariç 2/1) · h1 (her zaman %25)
        '_sinirFazlasi' => 'oransal', // oransal: ana-baba %25, kalan %75 kazalı/eş/çocuk 2-2-1 (canlıda ölçülen) · kazali: fazlası açıkta · dagit: fazlası eş/çocuğa
        // Aktüeryal hayatta kalma olasılığı — varsayılanlar 02_aktuer (destek) ve 27_isgucu_aktuer ölçümleriyle %0,01 tutan kural:
        // l(x) bakiye ömürden türetilir, başlangıç yaşı rapor tarihindeki yaşın yuvarlanmışı,
        // aktif satır n'de l(y+n−1)/l(y), pasif satırlarda aktif dönemin kısmi satırı varsa adım +1.
        '_aktuerYas' => 'Ryuv',       // G/Gtam/Gyuv/Gtavan (geçmiş sonu) · O/Otam (olay) · R/Rtam/Ryuv (rapor)
        '_aktuerKayma' => 0,          // destek: 1 ise n. satırda l(y+n)/l(y)
        '_aktuerKaymaIsgucu' => 0,    // iş gücü kaybı: aynı kayma
        '_aktuerIsgucuDonem' => 'hepsi', // iş gücü kaybında hayatta kalma olasılığı: hepsi · pasif (yalnızca pasif dönemde)
        '_lx' => 'turetilmis',        // TRH-2010 l(x): turetilmis (bakiye ömürden; canlıya uyan) · tablo (yayımlanmış l(x))
        '_pmfDuzelt' => '20,40,46,56,58,67,75,82', // PMF-1931'de komşu ortalamasıyla düzeltilen yaşlar ("-" hiçbiri); 46/58/67 eklenince 54 aktüer %0,01'e indi
        '_gigGun' => 'gun+1',         // GİG satırındaki gün sayısı: gun+1 (olay ve bitiş günü dahil; 121/122 birebir) · gun
        '_gigHaric' => 'gun+1',       // sürekli iş gücü kaybından çıkarılan gün sayısı: gun+1 (118 ölçümü) · gun
        '_askerBas' => 'yil',         // askerlik başlangıcı: yil (askerlik yaşının dolduğu yılın 1 Ocak'ı) · dogum (doğum günü)
        '_teknikUs' => 'adim',        // teknik faiz indirgeme üssü: adim (hayatta kalma adımıyla aynı; 47_teknik_faiz_3 birebir) · n (satır − 1)
        '_aktuerPasifKayma' => 'kismi', // pasif satırlarda hayatta kalma adımına ek: kismi (aktifte kısmi satır varsa 1) · 0 · 1
        // Kalan destek yılı — varsayılanlar ölçülen 56 kalemin hepsini %0,04 içinde tutan kural (23_kadin_kazali ile belirlendi):
        '_faizVeriSonu' => null,      // faiz oranlarında bu tarihten sonraki değişiklikleri yok say (canlı site 31.07.2026 %31'i uygulamıyor)
        '_avansSerisi' => 'avans_canli', // avans_canli (canlı sitenin serisi) · avans (TCMB yürürlük tarihleri) · avans_donemsel (3095 m.2 altı aylık)
        '_sgkPsdOtomatik' => true,    // SGK aylık gelirinden PSD tariften hesaplanır (canlı site üye olmayan isteklerde uygulamıyor)
        '_esSinirsiz' => true,        // kazalının ömrüyle sınırlanan kişi kalan yıla takılmadan ömür sonuna kadar pay alır
        '_pasifSatirKalan' => 'tavan', // kalan yıl karşılaştırmasında pasif satır no: tavan (aktif satır sayısı, kısmi dahil, + k) · tam (aktif tam yıl + k)
        '_kismiSatir' => 'n',         // kısmi satır da normal satır gibi yıl tüketir · n-1 (tüketmez)
        '_kalan' => 'yil',            // gelecek dönemde kalan destek yılı: yil = destek yılı − geçmiş yıl (yarımda aşağı) · tarih = ⌊(olay + destek yılı − geçmiş sonu) / yıl⌋
    ];

    private const SAYILAR = ['kusur', 'ucret', 'teknikFaiz', 'yetistirmeOrani', 'yetistirmeGelir', 'kazaliSgkGelir', 'isgucuOrani', 'kazaliPsd', 'geciciGun', 'gio', 'bakiciOrani', 'bakiciGun', 'artis', 'iskonto',
        'aktifBaslamaYasi', 'aktifBitisYasi', 'pasifGelir', 'erkekDestekSonu', 'kizDestekSonu', 'universiteDestekSonu', 'faraziEvlenme',
        'farazi1Cocuk', 'farazi2Cocuk', 'askerlikYasi', 'askerlikSuresi'];

    private const MANTIKSAL = ['aylikBaz', 'gelirVergisiz', 'gecmisGercekUcret', 'esCocukAyri', 'tamHayat', 'geciciHesapla', 'bakiciHesapla',
        'yetistirme', 'yetistirmeAnneYok', 'ilaveAgi', 'bakiciNet', 'askerlikDus', 'gecmisRaporTarihiIle', 'agisiz', 'esCalisiyor', 'bakiyeRaporTarihine',
        'yasYukariYuvarla', 'evlenmeOlayTarihine', 'anneBabaYarimPay', 'anneBaba25SinirYok', 'anneBabaYerineCocuk'];

    public static function normalize(array $ham): array
    {
        $g = self::VARSAYILAN;
        foreach ($g as $k => $v) {
            if (array_key_exists($k, $ham)) {
                $g[$k] = $ham[$k];
            }
        }
        // Farazi evlenme yaşları boşsa canlı sitenin cinsiyete göre varsayılanı
        $kadin = strtoupper((string) $g['cinsiyet']) === 'K';
        foreach (['faraziEvlenme' => [28, 25], 'farazi1Cocuk' => [30, 27], 'farazi2Cocuk' => [32, 29]] as $k => [$erkek, $kadinYas]) {
            if ($g[$k] === null || $g[$k] === '') {
                $g[$k] = $kadin ? $kadinYas : $erkek;
            }
        }
        foreach (['faizBaslangic', 'policeTarihi', 'kazaliSgkBaslangic', 'sgkBaslangic'] as $k) {
            $g[$k] = Tarih::gun($g[$k]);
        }
        $g['faizTuru'] = $g['faizTuru'] === 'avans' ? 'avans' : 'yasal';
        foreach (self::SAYILAR as $k) {
            $g[$k] = Sayi::oku($g[$k]);
        }
        foreach (self::MANTIKSAL as $k) {
            $g[$k] = Sayi::mantiksal($g[$k]);
        }
        foreach (['olayTarihi', 'dogumTarihi', 'raporTarihi'] as $k) {
            $g[$k] = Tarih::gun($g[$k]);
        }
        $g['kusur'] = max(0.0, min(100.0, $g['kusur']));
        $g['cinsiyet'] = strtoupper((string) $g['cinsiyet']) === 'K' ? 'K' : 'E';
        foreach (['mod' => ['destek', 'isgucu'], 'kazaTuru' => ['is', 'trafik'], 'yontem' => ['progresif', 'aktuer'],
            'tablo' => ['trh', 'pmf', 'cso', 'gatt'], 'paylastirma' => ['21', 'ayim', '70']] as $k => $izinli) {
            $g[$k] = in_array((string) $g[$k], $izinli, true) ? (string) $g[$k] : $izinli[0];
        }

        $hamSatirlar = [];
        foreach ($ham['hakSahipleri'] ?? [] as $i => $s) {
            $anahtar = is_string($i) ? $i : ($s['anahtar'] ?? null);
            if ($anahtar !== null) {
                $hamSatirlar[$anahtar] = $s;
            }
        }
        $g['hakSahipleri'] = [];
        foreach (self::HAK_SAHIPLERI as $anahtar => $tanim) {
            $s = $hamSatirlar[$anahtar] ?? [];
            $varsayilanCins = match ($tanim['tur']) {
                'anne' => 'K',
                'baba' => 'E',
                'es' => $g['cinsiyet'] === 'E' ? 'K' : 'E',
                default => 'E',
            };
            $cins = strtoupper((string) ($s['cinsiyet'] ?? $varsayilanCins)) === 'K' ? 'K' : 'E';
            $g['hakSahipleri'][] = [
                'anahtar' => $anahtar,
                'etiket' => $tanim['etiket'],
                'tur' => $tanim['tur'],
                'ad' => trim((string) ($s['ad'] ?? '')),
                'hesapla' => Sayi::mantiksal($s['hesapla'] ?? false),
                'dogumTarihi' => Tarih::gun($s['dogumTarihi'] ?? null),
                'cinsiyet' => $cins,
                'yuksekOgrenim' => Sayi::mantiksal($s['yuksekOgrenim'] ?? false),
                'psd' => Sayi::oku($s['psd'] ?? 0),
                'sgkGelir' => Sayi::oku($s['sgkGelir'] ?? 0),
                'destekSonu' => Tarih::gun($s['destekSonu'] ?? null),
            ];
        }

        return $g;
    }
}
