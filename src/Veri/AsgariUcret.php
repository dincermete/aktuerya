<?php

declare(strict_types=1);

namespace DestekTazminat\Veri;

use DestekTazminat\Destek\Tarih;

/**
 * Asgari ücret dönemleri: başlangıç, brüt, AGİ'siz net (bekâr).
 * 2008–2021 arası AGİ'li net = AGİ'siz net + brüt × %15 × %50.
 * 2022'den itibaren asgari ücret vergi dışı, AGİ yok.
 * Yeni yıl geldiğinde sadece bu listeye satır eklenir.
 */
final class AsgariUcret
{
    public const DONEMLER = [
        ['2005-01-01', 488.70, 350.15],
        ['2006-01-01', 531.00, 380.46],
        ['2007-01-01', 562.50, 403.02],
        ['2007-07-01', 585.00, 419.15],
        ['2008-01-01', 608.40, 435.92],
        ['2008-07-01', 638.70, 457.63],
        ['2009-01-01', 666.00, 477.19],
        ['2009-07-01', 693.00, 496.53],
        ['2010-01-01', 729.00, 521.89],
        ['2010-07-01', 760.50, 544.44],
        ['2011-01-01', 796.50, 570.22],
        ['2011-07-01', 837.00, 599.21],
        ['2012-01-01', 886.50, 634.64],
        ['2012-07-01', 940.50, 673.30],
        ['2013-01-01', 978.60, 699.61],
        ['2013-07-01', 1021.50, 730.28],
        ['2014-01-01', 1071.00, 765.67],
        ['2014-07-01', 1134.00, 810.70],
        ['2015-01-01', 1201.50, 858.96],
        ['2015-07-01', 1273.50, 910.43],
        ['2016-01-01', 1647.00, 1177.46],
        ['2017-01-01', 1777.50, 1270.75],
        ['2018-01-01', 2029.50, 1450.91],
        ['2019-01-01', 2558.40, 1829.02],
        ['2020-01-01', 2943.00, 2103.98],
        ['2021-01-01', 3577.50, 2557.59],
        ['2022-01-01', 5004.00, 4253.40],
        ['2022-07-01', 6471.00, 5500.35],
        ['2023-01-01', 10008.00, 8506.80],
        ['2023-07-01', 13414.50, 11402.32],
        ['2024-01-01', 20002.50, 17002.12],
        ['2025-01-01', 26005.50, 22104.67],
        ['2026-01-01', 33030.00, 28075.50],
    ];

    /** @var list<array{i:int,gun:int,brut:float,net:float}>|null */
    private static ?array $liste = null;

    /** @return array{i:int,gun:int,brut:float,net:float} */
    public static function donem(int $gun): array
    {
        $liste = self::liste();
        $lo = 0;
        $hi = count($liste) - 1;
        if ($gun < $liste[0]['gun']) {
            return $liste[0];
        }
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi + 1, 2);
            if ($liste[$mid]['gun'] <= $gun) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }

        return $liste[$lo];
    }

    /**
     * Gelir ve damga vergisi kesilmiş (AGİ'siz, "G.V. istisnası düşülmüş") net.
     * 2021'e kadar tablodaki net zaten budur; 2022'den sonra vergi istisnası olmasaydı ele geçecek tutar hesaplanır:
     * brüt − SGK %14 − işsizlik %1 − gelir vergisi %15 × (brüt × 0,85) − damga %0,759.
     */
    public static function agisizNet(int $gun): float
    {
        $d = self::donem($gun);
        if (Tarih::yil($d['gun']) < 2022) {
            return $d['net'];
        }
        $matrah = $d['brut'] * 0.85;

        return round($matrah - $matrah * 0.15 - $d['brut'] * 0.00759, 2);
    }

    public static function agiUygulanir(int $gun): bool
    {
        $y = Tarih::yil($gun);

        return $y >= 2008 && $y <= 2021;
    }

    /** @return list<array{i:int,gun:int,brut:float,net:float}> */
    public static function liste(): array
    {
        if (self::$liste === null) {
            self::$liste = [];
            foreach (self::DONEMLER as $i => [$bas, $brut, $net]) {
                self::$liste[] = ['i' => $i, 'gun' => (int) Tarih::gun($bas), 'brut' => $brut, 'net' => $net];
            }
        }

        return self::$liste;
    }
}
