<?php

declare(strict_types=1);

namespace DestekTazminat\Veri;

/**
 * AYİM yeniden evlenme olasılığı tablosu (çocuksuz eş için %).
 * Erkek eş satırları kadın oranlarının DİE %77,13 katsayısıyla uyarlanmış halidir.
 */
final class EvlenmeIhtimali
{
    private const KADIN = [[20, 52], [25, 40], [30, 27], [35, 17], [40, 9], [50, 2], [55, 1]];
    private const ERKEK = [[20, 90], [25, 70], [30, 48], [35, 30], [40, 15], [50, 4], [55, 2]];

    public static function oran(int $yas, string $cinsiyet): int
    {
        foreach ($cinsiyet === 'E' ? self::ERKEK : self::KADIN as [$ust, $oran]) {
            if ($yas <= $ust) {
                return $oran;
            }
        }

        return 0;
    }

    /** Destek alan her çocuk için 5 puan düşülür. */
    public static function indirimli(int $yas, string $cinsiyet, int $cocukSayisi): int
    {
        return max(0, self::oran($yas, $cinsiyet) - 5 * $cocukSayisi);
    }
}
