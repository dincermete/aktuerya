<?php

declare(strict_types=1);

namespace DestekTazminat\Destek;

/**
 * Tarihler motor içinde "gün numarası" (1970-01-01'den beri gün, UTC) olarak taşınır.
 * Böylece saat dilimi ve yaz saati kaymaları hesabı etkilemez.
 */
final class Tarih
{
    /** "Y-m-d" veya "d.m.Y" metnini gün numarasına çevirir; geçersiz/boş/1900 ise null. */
    public static function gun(mixed $deger): ?int
    {
        if ($deger === null || $deger === '') {
            return null;
        }
        if (is_int($deger)) {
            return $deger;
        }
        $s = trim((string) $deger);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            [$y, $ay, $g] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
            [$g, $ay, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }
        if ($y < 1901 || !checkdate($ay, $g, $y)) {
            return null;
        }

        return self::ymd($y, $ay, $g);
    }

    /** Taşan ay/gün değerlerini normalleştirir (13. ay = sonraki yılın ocağı). */
    public static function ymd(int $yil, int $ay, int $gun): int
    {
        return (int) floor(gmmktime(0, 0, 0, $ay, $gun, $yil) / 86400);
    }

    /** @return array{0:int,1:int,2:int} yıl, ay, gün */
    public static function parcala(int $gun): array
    {
        $t = $gun * 86400;

        return [(int) gmdate('Y', $t), (int) gmdate('n', $t), (int) gmdate('j', $t)];
    }

    public static function yil(int $gun): int
    {
        return (int) gmdate('Y', $gun * 86400);
    }

    public static function yaz(int|float $gun): string
    {
        return gmdate('d.m.Y', (int) floor($gun) * 86400);
    }

    public static function iso(int|float $gun): string
    {
        return gmdate('Y-m-d', (int) floor($gun) * 86400);
    }

    /** Tam yılları takvim üzerinden, küsuratı 365,25 günlük yıl üzerinden ekler. */
    public static function yilEkle(int $gun, float $yil): int
    {
        $tam = (int) floor($yil);
        $kusurat = $yil - $tam;
        [$y, $a, $g] = self::parcala($gun);

        return self::ymd($y + $tam, $a, $g) + (int) round($kusurat * 365.25);
    }

    public static function ayEkle(int $gun, int $ay): int
    {
        [$y, $a, $g] = self::parcala($gun);

        return self::ymd($y, $a + $ay, $g);
    }

    /** Doğum gününden itibaren tam yaş + son doğum gününden geçen kısım. */
    public static function yas(int $dogum, int $gun): float
    {
        [$dy, $da, $dg] = self::parcala($dogum);
        [$y, $a, $g] = self::parcala($gun);
        $tam = $y - $dy;
        if ($a < $da || ($a === $da && $g < $dg)) {
            $tam--;
        }

        return $tam + ($gun - self::yilEkle($dogum, $tam)) / 365.25;
    }

    /** İki tarih arasındaki tam takvim ayı (gün eksikse ay sayılmaz). */
    public static function ayFarki(int $bas, int $son): int
    {
        if ($son <= $bas) {
            return 0;
        }
        [$y1, $a1, $g1] = self::parcala($bas);
        [$y2, $a2, $g2] = self::parcala($son);
        $ay = ($y2 - $y1) * 12 + ($a2 - $a1);
        if ($g2 < $g1) {
            $ay--;
        }

        return max(0, $ay);
    }

    /**
     * Kazalının ömür sonuyla sınırlanan destek yılı (canlı sitede ölçülen, VBScript DateDiff benzeri):
     * takvim yılı farkı + (toplam ay farkının 12'ye kalanı ≥ 6 ise 1).
     * 14.03.2023→12.10.2064: 41+1=42 · 20.08.2019→31.01.2064: 45 · 14.03.2023→04.05.2039: 16 · 10.05.2015→06.08.2064: 49
     */
    public static function destekYiliTakvim(int $bas, int $son): int
    {
        if ($son <= $bas) {
            return 0;
        }
        [$y1, $a1] = self::parcala($bas);
        [$y2, $a2] = self::parcala($son);
        $toplamAy = ($y2 - $y1) * 12 + ($a2 - $a1);

        return ($y2 - $y1) + ((($toplamAy % 12) + 12) % 12 >= 6 ? 1 : 0);
    }

    /** Günleri yok sayan ay farkı (VBScript DateDiff("m") gibi): 13.09.2026 → 12.06.2049 = 273. */
    public static function ayFarkiGunsuz(int $bas, int $son): int
    {
        if ($son <= $bas) {
            return 0;
        }
        [$y1, $a1] = self::parcala($bas);
        [$y2, $a2] = self::parcala($son);

        return max(0, ($y2 - $y1) * 12 + ($a2 - $a1));
    }

    /** 41,58 → "41 yıl 7 ay" (ay yuvarlanır). */
    public static function yilAy(float $yil): string
    {
        if ($yil <= 0) {
            return '0 yıl 0 ay';
        }
        $tam = (int) floor($yil + 1e-9);
        $ay = (int) round(($yil - $tam) * 12);
        if ($ay >= 12) {
            $tam++;
            $ay = 0;
        }

        return $tam . ' yıl ' . $ay . ' ay';
    }
}
