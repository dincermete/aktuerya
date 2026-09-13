<?php

declare(strict_types=1);

namespace DestekTazminat\Destek;

final class Sayi
{
    /** "45.000,50", "45000.50", "%80", 45000 → float */
    public static function oku(mixed $deger): float
    {
        if (is_int($deger) || is_float($deger)) {
            return (float) $deger;
        }
        $s = preg_replace('/\s|TL|₺|%/u', '', (string) $deger) ?? '';
        if ($s === '') {
            return 0.0;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (!preg_match('/^-?\d+\.\d{1,2}$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    public static function mantiksal(mixed $deger): bool
    {
        if (is_bool($deger)) {
            return $deger;
        }

        return in_array(strtolower(trim((string) $deger)), ['1', 'on', 'true', 'evet', 'yes'], true);
    }

    /** Pay etiketleri için: 2 → "2", 0.5 → "0,5", 11.666 → "11,67" */
    public static function kisa(float $x): string
    {
        if (abs($x - round($x)) < 1e-9) {
            return (string) (int) round($x);
        }

        return str_replace('.', ',', rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.'));
    }

    public static function tl(float $x, int $basamak = 2): string
    {
        return number_format($x, $basamak, ',', '.') . ' TL';
    }
}
