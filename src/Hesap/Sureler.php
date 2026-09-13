<?php

declare(strict_types=1);

namespace DestekTazminat\Hesap;

use DestekTazminat\Destek\Tarih;

/**
 * Dönem süreleri (ay). Aktif ve pasif ay sayıları gelecek dönem tutarını doğrudan belirler,
 * diğerleri yalnızca ekranda gösterilir. Kurallar canlı siteyle karşılaştırılarak belirlendi (tests/canli/sureler.php).
 */
final class Sureler
{
    /** @return array{olayYasi:int,bakiyeOmur:int,faalCalisma:int,gecmisDonem:int,aktifDonem:int,pasifDonem:int} */
    public static function hesapla(int $dogum, int $olay, int $gecmisSonu, int $aktifSonu, float $bakiyeOmur, float $aktifBasYas, float $aktifSonYas): array
    {
        // 29 vakanın hepsinde tutan kurallar:
        //  - geçmiş ve aktif dönem: gün farkı × 12 ÷ 365,25, tabana
        //  - yaş, faal çalışma ve pasif dönem: yaş = gün farkı ÷ 365 (artık yıl yok sayılır)
        $ay = static fn (int $bas, int $son): float => max(0, $son - $bas) * 12 / 365.25;
        $gecmis = (int) floor($ay($olay, $gecmisSonu) + 1e-9);
        // Aktif dönem (ekran): son gün hariç ⌊(aktif sonu − 1 − geçmiş sonu) × 12 ÷ 365,25⌋
        // (90_farazi_28_yasli_bekar 8432 gün → 276 ay · 08_bekar 12543 gün → 412 ay)
        $aktif = $aktifSonu > $gecmisSonu ? (int) floor($ay($gecmisSonu, $aktifSonu - 1) + 1e-9) : 0;
        $bakiye = (int) floor($bakiyeOmur * 12 + 0.05);
        $yas365 = ($olay - $dogum) / 365;
        $pasif = $yas365 >= $aktifSonYas
            ? max(0, $bakiye - $gecmis)
            : max(0, (int) floor(($bakiyeOmur + $yas365 - $aktifSonYas) * 12 + 0.05));

        return [
            'olayYasi' => (int) round($ay($dogum, $olay)),
            'bakiyeOmur' => $bakiye,
            // 18 yaş altı kazalıda da canlı site 60 − yaş gösteriyor (76_yetistirme_yok: 14,8 yaş → 45 yıl 2 ay)
            'faalCalisma' => max(0, (int) floor(($aktifSonYas - $yas365) * 12 + 1e-9)),
            'gecmisDonem' => $gecmis,
            'aktifDonem' => $aktif,
            'pasifDonem' => $pasif,
        ];
    }
}
