<?php

declare(strict_types=1);

namespace DestekTazminat\Veri;

/**
 * Bakiye ömür (e(x)) tabloları ve bunlardan türetilen yaşayan sayıları (l(x)).
 * Kaynak: TRH-2010 ve PMF-1931 tabloları (tazminathukuku.com).
 */
final class YasamTablosu
{
    public const TRH_ERKEK = [
        71.93, 72.35, 71.42, 70.47, 69.52, 68.57, 67.60, 66.63, 65.66, 64.68, 63.70, 62.72, 61.74, 60.76, 59.78, 58.80, 57.84, 56.87, 55.91, 54.95,
        53.99, 53.04, 52.09, 51.14, 50.19, 49.24, 48.28, 47.33, 46.37, 45.41, 44.45, 43.50, 42.54, 41.58, 40.62, 39.67, 38.72, 37.77, 36.81, 35.87,
        34.93, 33.99, 33.05, 32.12, 31.19, 30.27, 29.36, 28.46, 27.56, 26.67, 25.79, 24.93, 24.06, 23.21, 22.37, 21.54, 20.74, 19.94, 19.15, 18.38,
        17.62, 16.88, 16.14, 15.42, 14.72, 14.04, 13.37, 12.72, 12.08, 11.47, 10.87, 10.29, 9.73, 9.20, 8.68, 8.17, 7.69, 7.24, 6.81, 6.40,
        5.99, 5.59, 5.23, 4.90, 4.57, 4.25, 3.93, 3.64, 3.37, 3.12, 2.90, 2.66, 2.39, 2.10, 1.80, 1.55, 1.40, 1.23,
    ];

    public const TRH_KADIN = [
        78.02, 77.66, 76.68, 75.70, 74.72, 73.73, 72.74, 71.75, 70.76, 69.76, 68.77, 67.78, 66.78, 65.79, 64.79, 63.80, 62.81, 61.82, 60.83, 59.84,
        58.85, 57.86, 56.88, 55.89, 54.90, 53.92, 52.93, 51.95, 50.97, 49.98, 49.00, 48.02, 47.04, 46.06, 45.08, 44.10, 43.12, 42.15, 41.17, 40.20,
        39.23, 38.26, 37.30, 36.34, 35.38, 34.43, 33.48, 32.54, 31.60, 30.67, 29.74, 28.82, 27.90, 26.98, 26.08, 25.18, 24.29, 23.40, 22.52, 21.65,
        20.79, 19.94, 19.09, 18.26, 17.43, 16.63, 15.85, 15.08, 14.32, 13.58, 12.87, 12.18, 11.51, 10.85, 10.22, 9.62, 9.05, 8.51, 8.00, 7.50,
        7.01, 6.55, 6.12, 5.71, 5.32, 4.93, 4.54, 4.20, 3.88, 3.59, 3.29, 2.97, 2.64, 2.32, 1.99, 1.67, 1.36, 1.05,
    ];

    /** TRH-2010 yaşayan sayıları (l(x), 100.000 doğum), 0–97 yaş. Aktüeryal yöntemde hayatta kalma olasılığı için. */
    public const TRH_LX_ERKEK = [
        100000, 98047, 97960, 97884, 97816, 97754, 97700, 97658, 97623, 97591, 97560, 97529, 97496, 97462, 97434, 97390, 97336, 97268, 97188, 97096,
        96996, 96891, 96782, 96669, 96554, 96435, 96315, 96196, 96077, 95958, 95838, 95720, 95597, 95467, 95326, 95172, 95002, 94817, 94612, 94380,
        94119, 93829, 93505, 93144, 92745, 92302, 91811, 91276, 90699, 90088, 89454, 88796, 88109, 87390, 86640, 85855, 85033, 84177, 83284, 82356,
        81387, 80387, 79344, 78255, 77107, 75910, 74660, 73357, 71988, 70563, 69062, 67491, 65842, 64113, 62289, 60372, 58362, 56255, 54050, 51743,
        49339, 46843, 44266, 41599, 38866, 36088, 33261, 30380, 27428, 24424, 21398, 18366, 15319, 12268, 9240, 6386, 3879, 1917,
    ];

    public const TRH_LX_KADIN = [
        100000, 99184, 99156, 99133, 99113, 99094, 99080, 99069, 99059, 99050, 99040, 99031, 99022, 99014, 99005, 98993, 98980, 98965, 98949, 98932,
        98912, 98892, 98869, 98845, 98820, 98793, 98766, 98736, 98706, 98673, 98638, 98601, 98563, 98523, 98479, 98429, 98376, 98321, 98261, 98193,
        98115, 98031, 97942, 97842, 97727, 97592, 97438, 97270, 97083, 96874, 96639, 96383, 96109, 95808, 95471, 95089, 94670, 94222, 93730, 93179,
        92556, 91885, 91175, 90390, 89493, 88447, 87278, 86010, 84605, 83025, 81233, 79246, 77088, 74735, 72162, 69341, 66226, 62832, 59233, 55503,
        51715, 47788, 43672, 39491, 35365, 31416, 27560, 23717, 20012, 16571, 13518, 10863, 8523, 6483, 4729, 3246, 2045, 1134,
    ];

    /** Yayımlanan PMF tablosu 0–84 yaş ve 90/95/100/105 düğümleri. */
    private const PMF_HAM = [
        56.64, 60.60, 60.58, 59.97, 59.22, 58.41, 57.57, 56.71, 55.83, 54.93, 54.03, 53.11, 52.19, 51.28, 50.37, 49.49, 48.62, 47.78, 46.96, 46.15,
        45.90, 44.59, 43.83, 43.03, 42.27, 41.49, 40.70, 39.90, 39.10, 38.32, 37.50, 36.70, 35.90, 35.10, 34.29, 33.49, 32.69, 31.90, 31.10, 30.31,
        29.73, 28.73, 27.95, 27.18, 26.40, 25.64, 24.78, 24.12, 23.36, 22.62, 21.88, 21.15, 20.42, 19.70, 18.98, 18.28, 17.82, 16.90, 16.10, 15.55,
        14.89, 14.23, 13.59, 12.97, 12.35, 11.75, 11.17, 10.51, 10.05, 9.50, 8.98, 8.47, 7.98, 7.54, 7.08, 6.88, 6.25, 5.86, 5.50, 5.15,
        4.85, 4.52, 5.22, 3.95, 3.71,
    ];

    /** @var array<string, list<float>> */
    private static array $e = [];
    /** @var array<string, list<float>> */
    private static array $l = [];

    /** Tablo + cinsiyet için önbellek anahtarı. PMF-1931 ve GATT-1983 tek cinsiyetlidir. */
    private static function anahtar(string $tablo, string $cinsiyet): string
    {
        return match ($tablo) {
            'pmf' => 'pmf',
            'gatt' => 'gatt',
            'cso' => $cinsiyet === 'K' ? 'csoK' : 'csoE',
            default => $cinsiyet === 'K' ? 'trhK' : 'trhE',
        };
    }

    /** @return list<float> */
    public static function dizi(string $tablo, string $cinsiyet): array
    {
        $anahtar = self::anahtar($tablo, $cinsiyet);
        if (!isset(self::$e[$anahtar])) {
            self::$e[$anahtar] = match ($anahtar) {
                'pmf' => self::pmf(),
                'trhK' => self::TRH_KADIN,
                'trhE' => self::TRH_ERKEK,
                default => self::disTablo($anahtar),
            };
        }

        return self::$e[$anahtar];
    }

    /**
     * CSO-1980 (Türk yayınlarındaki bakiye ömür tablosu, SOA ANB ile doğrulandı) ve GATT-1983 unisex (SOA tablo 844'ten hesaplandı).
     * Canlı site karşılaştırması: CSO erkek 33 yaş 40,46 → 485 ay · GATT 33 yaş 47,92 → 575 ay (birebir).
     * @return list<float>
     */
    private static function disTablo(string $anahtar): array
    {
        static $veri = null;
        $veri ??= json_decode((string) file_get_contents(__DIR__ . '/veri/yasam_tablolari_cso_gatt.json'), true, 512, JSON_THROW_ON_ERROR);
        $ham = match ($anahtar) {
            'csoE' => $veri['CSO1980']['erkek']['ex'],
            'csoK' => $veri['CSO1980']['kadin']['ex'],
            default => $veri['GATT1983']['unisex']['ex'],
        };
        // Boş uçları doldur: başta ilk dolu değer + fark, sonda sıfıra inen değer yok sayılır
        $ex = [];
        $ilk = null;
        foreach ($ham as $i => $v) {
            if ($v !== null) {
                $ilk ??= $i;
                $ex[$i] = (float) $v;
            }
        }
        for ($i = $ilk - 1; $i >= 0; $i--) {
            $ex[$i] = $ex[$i + 1] + 1;
        }
        ksort($ex);

        return array_values($ex);
    }

    /** Yaş varsayılan olarak tam yıla indirilir; $yuvarla ile en yakın tam yıla yuvarlanır (canlı sitedeki "üste yuvarla"). */
    public static function bakiye(string $tablo, string $cinsiyet, float $yas, bool $yuvarla = false): float
    {
        $t = self::dizi($tablo, $cinsiyet);
        $x = self::tamYas($yas, $yuvarla);
        $x = max(0, min(count($t) - 1, $x));

        return $t[$x];
    }

    public static function tamYas(float $yas, bool $yuvarla = false): int
    {
        return max(0, $yuvarla ? (int) round($yas) : (int) floor($yas + 1e-6));
    }

    /**
     * Yaşayan sayısı, ara yaşlarda doğrusal. TRH-2010 için yayımlanmış l(x) kullanılır;
     * PMF-1931 için l(x) bakiye ömürden türetilir: p(x) = (e(x) − ½) / (e(x+1) + ½).
     */
    public static function yasayan(string $tablo, string $cinsiyet, float $x, bool $turetilmis = false): float
    {
        $anahtar = self::anahtar($tablo, $cinsiyet);
        if (($anahtar === 'trhE' || $anahtar === 'trhK') && !$turetilmis) {
            self::$l[$anahtar] ??= array_merge(array_map('floatval', $anahtar === 'trhK' ? self::TRH_LX_KADIN : self::TRH_LX_ERKEK), [0.0]);
        } elseif ($anahtar === 'trhE' || $anahtar === 'trhK') {
            $anahtar .= '_turetilmis';
        }
        if (!isset(self::$l[$anahtar])) {
            $e = self::dizi($tablo, $cinsiyet);
            $l = [100000.0];
            for ($i = 0; $i < count($e) - 1; $i++) {
                $p = max(0.0, min(1.0, ($e[$i] - 0.5) / ($e[$i + 1] + 0.5)));
                $l[] = $l[$i] * $p;
            }
            $l[] = 0.0;
            self::$l[$anahtar] = $l;
        }
        $l = self::$l[$anahtar];
        $x = max(0.0, $x);
        if ($x >= count($l) - 1) {
            return 0.0;
        }
        $i = (int) floor($x);

        return $l[$i] + ($l[$i + 1] - $l[$i]) * ($x - $i);
    }

    /** @var list<int> PMF-1931'de komşu ortalamasıyla düzeltilen yaşlar (karşılaştırma için değiştirilebilir) */
    public static array $pmfDuzeltilen = [20, 40, 46, 56, 58, 67, 75, 82];

    /** @return list<float> */
    private static function pmf(): array
    {
        $a = self::PMF_HAM;
        // Yayımlanan tablodaki dizgi hataları (20, 40, 56, 75, 82. yaş) komşu ortalamasıyla düzeltilir.
        foreach (self::$pmfDuzeltilen as $i) {
            $a[$i] = round(($a[$i - 1] + $a[$i + 1]) / 2, 3);
        }
        $dugum = [[84, 3.71], [90, 2.71], [95, 2.40], [100, 2.00], [105, 1.00]];
        for ($k = 0; $k < count($dugum) - 1; $k++) {
            [$x0, $y0] = $dugum[$k];
            [$x1, $y1] = $dugum[$k + 1];
            for ($x = $x0 + 1; $x <= $x1; $x++) {
                $a[$x] = $y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0);
            }
        }

        return $a;
    }
}
