<?php

declare(strict_types=1);

namespace DestekTazminat\Veri;

use DestekTazminat\Destek\Tarih;

/**
 * SGK tarafından bağlanan gelirin ilk peşin sermaye değeri (PSD).
 * PSD = aylık gelir × 12 × katsayı ÷ 100 (Tebliğ RG 28.09.2008/27011 m.9).
 *
 * Tablo sürümü gelire giriş tarihine göre seçilir:
 *  - 25.09.2012 sonrası: 2012/32 Genelge Ek-41 (TRH-2010)
 *  - Ekim 2008 – 25.09.2012: 2008 Tebliği Ek-27 (CSO-1980)
 *  - Ekim 2008 öncesi: Ek-26 (PMF-1931) — veri yok, 2008 tablosu kullanılır ve not düşülür
 *
 * Tablolar: T1 kadın sigortalının eşi · T2 ana/baba · T3 kız/erkek çocuk · T4 kadın sigortalı · T5 erkek sigortalının eşi · T6 erkek sigortalı.
 * Kaynak: veri/sgk_psd_tarifeleri.json (çıkarım ve doğrulama notları dosyanın içinde).
 */
final class SgkPsd
{
    private static ?array $veri = null;

    /** Gelire giriş tarihindeki yaş: 6 aydan küçük kesir atılır, 6 ay ve üstü tam yıl sayılır (m.9/3). */
    public static function yas(int $dogum, int $gelirBaslangic): int
    {
        $ay = Tarih::ayFarki($dogum, $gelirBaslangic);

        return intdiv($ay, 12) + ($ay % 12 >= 6 ? 1 : 0);
    }

    /**
     * @param string $tablo T1, T2_ana, T2_baba, T3_kiz_cocuk, T3_erkek_cocuk, T4, T5, T6
     * @return array{psd: float, katsayi: float, yas: int, tablo: string, surum: string, not: ?string}|null
     */
    public static function hesapla(float $aylikGelir, string $tablo, int $dogum, int $gelirBaslangic): ?array
    {
        if ($aylikGelir <= 0) {
            return null;
        }
        $veri = self::veri();
        $surum = $gelirBaslangic > Tarih::ymd(2012, 9, 25) ? 'genelge_2012_32' : 'tebligi_2008';
        $not = $gelirBaslangic < Tarih::ymd(2008, 10, 1)
            ? 'Gelire giriş Ekim 2008 öncesi; Ek-26 (PMF-1931) tablosu bulunamadığından 2008 tebliği tablosu kullanıldı.'
            : null;
        $yas = self::yas($dogum, $gelirBaslangic);
        $katsayi = $veri[$surum]['tablolar'][$tablo]['yas'][(string) $yas] ?? null;
        if ($katsayi === null) {
            return null;
        }

        return [
            'psd' => round($aylikGelir * 12 * (float) $katsayi / 100, 2),
            'katsayi' => (float) $katsayi,
            'yas' => $yas,
            'tablo' => $tablo,
            'surum' => $surum === 'genelge_2012_32' ? 'Ek-41 (TRH-2010)' : 'Ek-27 (CSO-1980)',
            'not' => $not,
        ];
    }

    /** Hak sahibinin türüne ve cinsiyetlerine göre tablo adı. */
    public static function tabloAdi(string $tur, string $kisiCinsiyet, string $kazaliCinsiyet): string
    {
        return match ($tur) {
            'es' => $kazaliCinsiyet === 'K' ? 'T1' : 'T5',
            'anne' => 'T2_ana',
            'baba' => 'T2_baba',
            'cocuk' => $kisiCinsiyet === 'K' ? 'T3_kiz_cocuk' : 'T3_erkek_cocuk',
            default => $kazaliCinsiyet === 'K' ? 'T4' : 'T6', // kazalının kendisi (sürekli iş göremezlik geliri)
        };
    }

    private static function veri(): array
    {
        return self::$veri ??= json_decode((string) file_get_contents(__DIR__ . '/veri/sgk_psd_tarifeleri.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
