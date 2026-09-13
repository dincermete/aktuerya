<?php

declare(strict_types=1);

namespace DestekTazminat\Veri;

use DestekTazminat\Destek\Tarih;

/**
 * İşlemiş faiz: basit faiz, tutar × oran × gün ÷ 365, oran değiştikçe dilimlenir.
 * Canlı site ölçümü: 01.01.2025 → 13.09.2026 yasal faiz = tutar × %24 × 620 ÷ 365 (birebir).
 *
 * Kaynaklar: yasal faiz 3095 s.K. m.1 (Cumhurbaşkanı Kararı 8485 RG 21.05.2024; 7589 s.K. RG 31.07.2026),
 * avans faizi TCMB reeskont/avans duyuruları. Yeni oran geldiğinde listeye satır eklenir.
 */
final class Faiz
{
    /** @var list<array{0:string,1:float}> */
    public const YASAL = [
        ['2004-07-01', 38.0], ['2005-05-01', 12.0], ['2006-01-01', 9.0], ['2024-06-01', 24.0], ['2026-07-31', 31.0],
    ];

    /** TCMB avans faiz oranı, yürürlük tarihleriyle. */
    public const AVANS = [
        ['2004-06-15', 42.0], ['2005-01-13', 35.0], ['2005-05-25', 30.0], ['2005-12-20', 25.0], ['2006-12-20', 29.0], ['2007-12-28', 27.0],
        ['2009-04-09', 20.0], ['2009-06-12', 19.0], ['2009-12-22', 16.0], ['2010-12-30', 15.0], ['2011-12-29', 17.75], ['2012-06-19', 16.5],
        ['2012-12-20', 13.75], ['2013-06-21', 11.0], ['2013-12-27', 11.75], ['2014-12-14', 10.5], ['2016-12-31', 9.75], ['2018-06-29', 19.5],
        ['2019-10-11', 18.25], ['2019-12-21', 13.75], ['2020-06-13', 10.0], ['2020-12-19', 16.75], ['2021-12-31', 15.75], ['2022-12-31', 10.75],
        ['2023-06-24', 16.75], ['2023-09-01', 26.75], ['2023-09-28', 31.75], ['2023-11-01', 36.75], ['2023-12-01', 41.75], ['2023-12-23', 44.25],
        ['2024-04-01', 51.75], ['2024-12-28', 49.25], ['2025-03-08', 44.25], ['2025-09-17', 42.25], ['2025-12-20', 39.75],
    ];

    /**
     * @param string $tur yasal | avans | avans_donemsel (3095 m.2: Ocak–Haziran 31 Aralık oranı, Temmuz–Aralık 30 Haziran oranı 5 puan değiştiyse)
     * @param int|null $veriSonu bu tarihten sonra yürürlüğe giren oranlar yok sayılır (karşılaştırma için)
     * @return array{tutar: float, carpan: float, dilimler: list<array{bas:string, son:string, gun:int, oran:float}>}
     */
    public static function hesapla(float $anapara, int $bas, int $son, string $tur = 'yasal', ?int $veriSonu = null): array
    {
        if ($son <= $bas || $anapara == 0.0) {
            return ['tutar' => 0.0, 'carpan' => 0.0, 'dilimler' => []];
        }
        $liste = self::liste($tur, $veriSonu);
        $carpan = 0.0;
        $dilimler = [];
        for ($i = 0; $i < count($liste); $i++) {
            [$d0, $oran] = $liste[$i];
            $d1 = $liste[$i + 1][0] ?? PHP_INT_MAX;
            $a = max($bas, $d0);
            $b = min($son, $d1);
            if ($b <= $a) {
                continue;
            }
            // Canlı site oran değişiminden önceki son günü hiçbir dilime katmıyor: eski oranlı dilim bir gün kısa
            // (01_base ve 12_eski_agi: %9 diliminde 1 gün eksik; yalnız %24 dilimi olan 46_faiz_bas_2025 birebir)
            $gun = $b - $a - ($b === $d1 && isset($liste[$i + 1]) ? 1 : 0);
            $carpan += $oran / 100 * $gun / 365;
            $dilimler[] = ['bas' => Tarih::yaz($a), 'son' => Tarih::yaz($b), 'gun' => $gun, 'oran' => $oran];
        }

        return ['tutar' => $anapara * $carpan, 'carpan' => $carpan, 'dilimler' => $dilimler];
    }

    /** @return list<array{0:int,1:float}> gün numarası ve oran, artan */
    private static function liste(string $tur, ?int $veriSonu): array
    {
        $ham = match ($tur) {
            'avans', 'avans_donemsel', 'avans_canli' => self::AVANS,
            default => self::YASAL,
        };
        if ($tur === 'avans_canli') {
            // Canlı sitenin kullandığı seri (faiz başlangıcı kaydırılarak ölçüldü, 2005–2026):
            // 3095 m.2/2 dönemsel avans oranı (Ocak: 31 Aralık oranı · Temmuz: 30 Haziran oranı 5 puan farklıysa),
            // 2023 Haziran'dan sonraki TCMB değişiklikleri yok; 01.06.2024'ten itibaren %53,25.
            // 29.06.2018 – 31.12.2020 arasında ise TCMB'nin gerçek değişiklik tarihleri kullanılıyor (fm_2019/2020 aylık taraması).
            $gercekBas = Tarih::ymd(2018, 6, 29);
            $gercekSon = Tarih::ymd(2021, 1, 1);
            $ds = self::liste('avans_donemsel', Tarih::ymd(2023, 12, 31));
            $gercek = self::liste('avans', null);
            $oranTarihte = function (array $liste, int $gun): float {
                $o = $liste[0][1];
                foreach ($liste as [$d, $r]) {
                    if ($d <= $gun) {
                        $o = $r;
                    }
                }

                return $o;
            };
            $ham = array_merge(
                array_filter($ds, fn ($s) => $s[0] < $gercekBas),
                [[$gercekBas, $oranTarihte($gercek, $gercekBas)]],
                array_filter($gercek, fn ($s) => $s[0] > $gercekBas && $s[0] < $gercekSon),
                [[$gercekSon, $oranTarihte($ds, $gercekSon)]],
                array_filter($ds, fn ($s) => $s[0] > $gercekSon && $s[0] < Tarih::ymd(2024, 1, 1)),
                [[Tarih::ymd(2024, 6, 1), 53.25]],
            );
            $donemsel = [];
            foreach ($ham as $s) {
                if (!$donemsel || end($donemsel)[1] != $s[1]) {
                    $donemsel[] = $s;
                }
            }
            if ($veriSonu !== null) {
                $donemsel = array_values(array_filter($donemsel, fn ($s) => $s[0] <= $veriSonu));
            }

            return $donemsel;
        }
        $liste = [];
        foreach ($ham as [$tarih, $oran]) {
            $gun = (int) Tarih::gun($tarih);
            if ($veriSonu === null || $gun <= $veriSonu) {
                $liste[] = [$gun, $oran];
            }
        }
        if ($tur !== 'avans_donemsel') {
            return $liste;
        }
        // 3095 m.2 dönemsel seri
        $oranTarihte = function (int $gun) use ($liste): float {
            $o = $liste[0][1];
            foreach ($liste as [$d, $r]) {
                if ($d <= $gun) {
                    $o = $r;
                }
            }

            return $o;
        };
        $donemsel = [];
        for ($y = 2005; $y <= Tarih::yil($veriSonu ?? Tarih::ymd(2100, 1, 1)) && $y <= 2100; $y++) {
            $ilk = $oranTarihte(Tarih::ymd($y - 1, 12, 31));
            $haziran = $oranTarihte(Tarih::ymd($y, 6, 30));
            $donemsel[] = [Tarih::ymd($y, 1, 1), $ilk];
            $donemsel[] = [Tarih::ymd($y, 7, 1), abs($haziran - $ilk) >= 5 ? $haziran : $ilk];
            if ($veriSonu === null && $y >= (int) date('Y') + 1) {
                break;
            }
        }
        // Oranı değişmeyen dönemler birleştirilir (oran değişim sınırında bir gün sayılmadığı için önemli)
        $birlesik = [];
        foreach ($donemsel as $s) {
            if (!$birlesik || end($birlesik)[1] != $s[1]) {
                $birlesik[] = $s;
            }
        }

        return $birlesik;
    }
}
