<?php

declare(strict_types=1);

namespace DestekTazminat\Hesap;

use DestekTazminat\Destek\Sayi;

/**
 * Belirli bir günde destek alan kişilere düşen oranları dağıtır.
 * Kişi: ['id', 'tur' => es|cocuk|anne|baba, 'bas' => gün, 'son' => gün (hariç)].
 */
final class Paylastirma
{
    /** @var array<string, array<string, array{oran: float, etiket: string}>> */
    private array $onbellek = [];

    /** @param list<array> $kisiler gerçek ve farazi kişiler */
    public function __construct(
        private readonly string $yontem,
        private readonly bool $anneBabaYarim,
        private readonly bool $sinirYok,
        private readonly array $kisiler,
        private readonly string $yuzde70Varyant = 'd',
        private readonly string $sinirFazlasi = 'oransal',
        private readonly bool $ayimEssizIkiBir = false,
        private readonly bool $esCocukAyri = false,
        private readonly string $ayirVaryant = 'olay',
        private readonly ?float $ayirEbeveynPayi = null,
    ) {
    }

    /** @return array<string, array{oran: float, etiket: string}> */
    public function paylar(int $gun): array
    {
        $aktif = [];
        $imza = '';
        foreach ($this->kisiler as $k) {
            if ($gun >= $k['bas'] && $gun < $k['son']) {
                $aktif[] = $k;
                $imza .= $k['id'] . ',';
            }
        }

        return $this->onbellek[$imza] ??= $this->dagit($aktif);
    }

    private static function ebeveyn(array $k): bool
    {
        return $k['tur'] === 'anne' || $k['tur'] === 'baba';
    }

    private function dagit(array $aktif): array
    {
        $es = null;
        foreach ($aktif as $k) {
            if ($k['tur'] === 'es') {
                $es = $k;
            }
        }
        $yontem = $this->yontem;
        if ($yontem === 'ayim' && $es === null && $this->ayimEssizIkiBir) {
            $yontem = '21';
        }

        return match ($yontem) {
            'ayim' => $this->ayim($aktif, $es),
            '70' => $this->yuzdeYetmis($aktif, $es),
            default => $this->ikiBir($aktif),
        };
    }

    /** Kazalı 2, eş 2, çocuk ve ana-baba 1 pay; açıkta pay kalmaz. */
    private function ikiBir(array $aktif): array
    {
        $payda = 2.0;
        $agirlik = [];
        // "Anne-babaya çocukların yarısı pay": anne ve baba birlikte bir çocuk payı alır (ikisi varsa 0,5'er, tek kalırsa 1).
        // Ölçüm (17_anababayari): 1. çocuk 1/7, anne satır 12'de 1/12, satır 13–18'de 1/6.
        $ebeveynSayisi = count(array_filter($aktif, [self::class, 'ebeveyn']));
        $ebeveynAgirlik = $this->anneBabaYarim && $ebeveynSayisi > 1 ? 1.0 / $ebeveynSayisi : 1.0;
        foreach ($aktif as $k) {
            $w = match (true) {
                $k['tur'] === 'es' => 2.0,
                self::ebeveyn($k) => $ebeveynAgirlik,
                default => 1.0,
            };
            $agirlik[$k['id']] = $w;
            $payda += $w;
        }
        $out = [];
        foreach ($aktif as $k) {
            $out[$k['id']] = ['oran' => $agirlik[$k['id']] / $payda, 'etiket' => Sayi::kisa($agirlik[$k['id']]) . '/' . Sayi::kisa($payda)];
        }

        $ebeveynler = array_filter($aktif, [self::class, 'ebeveyn']);
        $digerleri = array_filter($aktif, fn ($k) => !self::ebeveyn($k));
        if ($this->esCocukAyri && $ebeveynler && $digerleri) {
            // "Eş ve çocuk paylarını anne babadan ayır": ana-babanın toplam payı olay tarihindeki kişilerle, ana ve baba ikisi de
            // varmış gibi 2/1'den bulunur ve sabit kalır; hayatta olan ebeveynler bu payı paylaşır, eş ve çocuklar kalanı kazalıyla 2-2-1 böler.
            // O satırdaki standart 2/1'de ana-baba toplamı %25'i aşıyorsa sınır uygulanır (toplam %25).
            // Ölçüm: 43/108 (eş+2 çocuk → %25) · 104/109 (eş+3 çocuk → 2/9) · 44 (eş → 1/3; ikisi birlikteyken %12,5'er) · 110 (2 çocuk → 1/3).
            $wEbeveyn = array_sum(array_map(fn ($k) => $agirlik[$k['id']], $ebeveynler));
            $wCocuk = array_sum(array_map(fn ($k) => $k['tur'] === 'es' ? 0.0 : $agirlik[$k['id']], $digerleri));
            $sinirda = !$this->sinirYok && ($this->ayirVaryant === 'h1' || $wEbeveyn / $payda > 0.25 + 1e-12);
            $ebeveynToplam = match (true) {
                $sinirda => 0.25,
                $this->ayirVaryant === 'olay' && $this->ayirEbeveynPayi !== null => $this->ayirEbeveynPayi,
                default => $wEbeveyn / (2 + $wCocuk + $wEbeveyn),
            };
            $wDiger = 2.0 + array_sum(array_map(fn ($k) => $agirlik[$k['id']], $digerleri));
            foreach ($ebeveynler as $k) {
                $o = $ebeveynToplam * $agirlik[$k['id']] / $wEbeveyn;
                $out[$k['id']] = ['oran' => $o, 'etiket' => '%' . Sayi::kisa($o * 100)];
            }
            foreach ($digerleri as $k) {
                $o = (1 - $ebeveynToplam) * $agirlik[$k['id']] / $wDiger;
                $out[$k['id']] = ['oran' => $o, 'etiket' => '%' . Sayi::kisa($o * 100)];
            }

            return $out;
        }

        if (!$this->sinirYok) {
            // Eş veya çocuk paylaşımdayken anne-babanın toplam payı %25'i aşamaz.
            $ebeveynler = array_filter($aktif, [self::class, 'ebeveyn']);
            $digerleri = array_filter($aktif, fn ($k) => !self::ebeveyn($k));
            $toplam = array_sum(array_map(fn ($k) => $out[$k['id']]['oran'], $ebeveynler));
            if ($digerleri && $toplam > 0.25 + 1e-12 && $this->sinirFazlasi === 'oransal') {
                // Canlı site: anne-babaya toplam %25 (kendi ağırlıklarıyla), kalan %75 kazalı (2), eş (2) ve çocuklar (1) arasında.
                // Ölçüm (01_base, 12. yıl): anne %12,5 · baba %12,5 · eş %30 · çocuk %15 · kazalı %30.
                $wEbeveyn = array_sum(array_map(fn ($k) => $agirlik[$k['id']], $ebeveynler));
                $wDiger = 2.0 + array_sum(array_map(fn ($k) => $agirlik[$k['id']], $digerleri));
                foreach ($ebeveynler as $k) {
                    $out[$k['id']] = ['oran' => 0.25 * $agirlik[$k['id']] / $wEbeveyn, 'etiket' => '%' . Sayi::kisa(25 * $agirlik[$k['id']] / $wEbeveyn)];
                }
                foreach ($digerleri as $k) {
                    $out[$k['id']] = ['oran' => 0.75 * $agirlik[$k['id']] / $wDiger, 'etiket' => '%' . Sayi::kisa(75 * $agirlik[$k['id']] / $wDiger)];
                }
            } elseif ($digerleri && $toplam > 0.25) {
                $olcek = 0.25 / $toplam;
                $fazla = $toplam - 0.25;
                $w = array_sum(array_map(fn ($k) => $agirlik[$k['id']], $digerleri));
                foreach ($ebeveynler as $k) {
                    $out[$k['id']]['oran'] *= $olcek;
                    $out[$k['id']]['etiket'] = '%' . Sayi::kisa($out[$k['id']]['oran'] * 100);
                }
                foreach ($digerleri as $k) {
                    if ($this->sinirFazlasi === 'dagit') {
                        $out[$k['id']]['oran'] += $fazla * $agirlik[$k['id']] / $w;
                    }
                    $out[$k['id']]['etiket'] = '%' . Sayi::kisa($out[$k['id']]['oran'] * 100);
                }
            }
        }

        return $out;
    }

    /**
     * AYİM paylaştırması (canlı sitenin açıklamasındaki tablo). Anne ve baba çocuk gibi sayılır.
     * Çocuksuz eşe %50 · 1: %45/%15 · 2: %40/%10 · 3: %35/%10 · 4: %30/%10 · 5: %30/%8 · 6: %29/%7 · 7: %29/%6.
     * Eş yoksa (veya destekten çıkmışsa) eşin payının yarısı çocuklara ve anne-babaya eşit dağıtılır.
     */
    private const AYIM = [0 => [50, 0], 1 => [45, 15], 2 => [40, 10], 3 => [35, 10], 4 => [30, 10], 5 => [30, 8], 6 => [29, 7], 7 => [29, 6]];

    private function ayim(array $aktif, ?array $es): array
    {
        $digerleri = array_values(array_filter($aktif, fn ($k) => $k['tur'] !== 'es'));
        $n = count($digerleri);
        if ($es === null && $n > 0 && count(array_filter($digerleri, [self::class, 'ebeveyn'])) === $n) {
            // Yalnızca ana-baba: çocuksuz eşin %50 payı hayatta olan ana-baba arasında bölünür (114_ayim_anababa: ikisi %25'er, tek kalan %50)
            $out = [];
            foreach ($digerleri as $k) {
                $out[$k['id']] = ['oran' => 0.5 / $n, 'etiket' => Sayi::kisa(50 / $n) . '/100'];
            }

            return $out;
        }
        [$esPay, $kisiPay] = self::AYIM[$n] ?? [29, 42 / $n];
        $out = [];
        if ($es !== null) {
            $out[$es['id']] = ['oran' => $esPay / 100, 'etiket' => Sayi::kisa($esPay) . '/100'];
        } elseif ($n > 0) {
            $kisiPay += $esPay / 2 / $n;
        }
        foreach ($digerleri as $k) {
            $out[$k['id']] = ['oran' => $kisiPay / 100, 'etiket' => Sayi::kisa($kisiPay) . '/100'];
        }

        // %25 sınırı: eş veya çocukla birlikteyken ana-baba toplamı %25'e indirilir; fazlası kazalı (2) ile eş (2) ve çocuklar (1) arasında bölünür.
        // Ölçüm (65_ayim_essiz): 2 çocuk + ana-baba → ana-baba %12,5'er, çocuk %13,75 + 2,5 ÷ 4 = %14,375 · tek çocuk + ana-baba → %15,83 + 6,67 ÷ 3.
        $ebeveynler = array_filter($digerleri, [self::class, 'ebeveyn']);
        $paylasanlar = array_filter($aktif, fn ($k) => !self::ebeveyn($k));
        $ebeveynToplam = array_sum(array_map(fn ($k) => $out[$k['id']]['oran'], $ebeveynler));
        if (!$this->sinirYok && $paylasanlar && $ebeveynToplam > 0.25 + 1e-12) {
            $fazla = $ebeveynToplam - 0.25;
            $w = 2.0 + array_sum(array_map(fn ($k) => $k['tur'] === 'es' ? 2.0 : 1.0, $paylasanlar));
            foreach ($ebeveynler as $k) {
                $out[$k['id']] = ['oran' => 0.25 / count($ebeveynler), 'etiket' => '%' . Sayi::kisa(25 / count($ebeveynler))];
            }
            foreach ($paylasanlar as $k) {
                $o = $out[$k['id']]['oran'] + $fazla * ($k['tur'] === 'es' ? 2.0 : 1.0) / $w;
                $out[$k['id']] = ['oran' => $o, 'etiket' => '%' . Sayi::kisa(round($o * 100, 3))];
            }
        }

        return $out;
    }

    /** 5510 sayılı Kanun oranlarıyla kazancın %70'i paylaştırılır; açıkta pay kalır. */
    private function yuzdeYetmis(array $aktif, ?array $es): array
    {
        $cocuklar = array_values(array_filter($aktif, fn ($k) => $k['tur'] === 'cocuk'));
        $ebeveynler = array_values(array_filter($aktif, [self::class, 'ebeveyn']));
        $esPay = $es ? (count($cocuklar) ? 50 : 75) : 0;
        $toplam = $esPay + 25 * count($cocuklar);
        if ($es === null && $this->yuzde70Varyant === 'd' && ($cocuklar || $ebeveynler)) {
            // Eş yokken kişi payı paylaşan kişi sayısına (N = çocuk + ana-baba) göre artar:
            // 100 × (N + 1) ÷ (N × (N + 2)), en çok %50 → N=1 %50 · 2 %37,5 · 3 %26,67 · 4 %20,83 · 5 %17,14.
            // Hesap %70 uygulanmış yüzdelerle yapılır ve her adım iki haneye yukarı yuvarlanır.
            // Ana-baba toplamı %25 × 0,7 = %17,5'i geçemez; çocuk payı %25'in altındaysa fazlalık çocuklar ve kazalı (2) arasında bölünür.
            // Ölçüm: 115, 123, 124, 116, 126, 127 · 125 (çocuk + ana-baba) · 69: 14,59 + 11,68 ÷ 4 = %17,51 · 134: 12 + 6,5 ÷ 5 = %13,30 · 135: 10,21 + 2,92 ÷ 6 = %10,70
            $yukari = fn (float $x): float => ceil(round($x * 100, 6)) / 100;
            $n = count($cocuklar) + count($ebeveynler);
            $ham = min(50.0, 100 * ($n + 1) / ($n * ($n + 2)));
            $kisiPay = $yukari($ham * 0.7);
            $fazla = $kisiPay * count($ebeveynler) - 17.5;
            if ($ebeveynler && $cocuklar && $fazla > 1e-9 && $ham < 25) {
                $kisiPay = $yukari($kisiPay + $fazla / (count($cocuklar) + 2));
            }
            $out = [];
            foreach ($cocuklar as $k) {
                $out[$k['id']] = ['oran' => $kisiPay / 100, 'etiket' => '%' . Sayi::kisa($kisiPay)];
            }
            foreach ($ebeveynler as $k) {
                $p = min($yukari($ham * 0.7), 17.5 / count($ebeveynler));
                $out[$k['id']] = ['oran' => $p / 100, 'etiket' => '%' . Sayi::kisa($p)];
            }

            return $out;
        }
        if ($this->yuzde70Varyant === 'd') {
            // Canlı site (05_yetmis ölçümü): ana-baba varsa toplam %25 alır (ikisi varsa %12,5'er),
            // eş ve çocuklar kalan %75'e (ana-baba yoksa %100'e) orantılı sığdırılır.
            $ebeveynToplam = $ebeveynler ? 25.0 : 0.0;
            if ($ebeveynler) {
                // Ana-babaya %25'er verip herkes %100'e orantılı sığdırıldığında daha az kalıyorsa o pay alınır
                // (71: eş + 5 çocuk + ana-baba → herkes × 100/225, ana-baba %11,1'er; 130: eş + 3 çocuk → %12,5'er)
                $herkes = $toplam + 25 * count($ebeveynler);
                $ebeveynToplam = min(25.0, 25 * count($ebeveynler) * min(1.0, 100 / $herkes));
            }
            $musait = 100.0 - $ebeveynToplam;
            $olcek = $toplam > $musait && $toplam > 0 ? $musait / $toplam : 1;
            $ebeveynPay = $ebeveynler ? $ebeveynToplam / count($ebeveynler) : 0;
        } elseif ($this->yuzde70Varyant === 'b') {
            // Ana-baba %25'er, herkes birlikte %100'e orantılı sığdırılır
            $hepsi = $toplam + 25 * count($ebeveynler);
            $olcek = $hepsi > 100 ? 100 / $hepsi : 1;
            $ebeveynPay = 25 * $olcek;
        } elseif ($this->yuzde70Varyant === 'c') {
            // Ana-baba her zaman %25'er; eş ve çocuklar kalan paya sığdırılır
            $ebeveynPay = $ebeveynler ? 25 : 0;
            $musait = 100 - 25 * count($ebeveynler);
            $olcek = $toplam > $musait && $toplam > 0 ? $musait / $toplam : 1;
        } else {
            $olcek = $toplam > 100 ? 100 / $toplam : 1;
            $kalan = 100 - $toplam * $olcek;
            $ebeveynPay = $ebeveynler ? min(25, $kalan / count($ebeveynler)) : 0;
        }
        $out = [];
        $ekle = function (array $k, float $yuzde) use (&$out) {
            $out[$k['id']] = ['oran' => $yuzde * 0.7 / 100, 'etiket' => '%' . Sayi::kisa($yuzde * 0.7)];
        };
        if ($es) {
            $ekle($es, $esPay * $olcek);
        }
        foreach ($cocuklar as $k) {
            $ekle($k, 25 * $olcek);
        }
        foreach ($ebeveynler as $k) {
            $ekle($k, $ebeveynPay);
        }

        return $out;
    }
}
