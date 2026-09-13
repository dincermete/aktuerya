<?php

declare(strict_types=1);

// Geçici: SGK PSD tarife JSON yapısını gösterir.
$d = json_decode((string) file_get_contents(__DIR__ . '/../veri/sgk_psd_tarifeleri.json'), true);
echo 'anahtarlar: ', implode(', ', array_keys($d)), "\n";
foreach (['tebligi_2008', 'genelge_2012_32'] as $surum) {
    echo "== $surum: ", implode(', ', array_keys($d[$surum])), "\n";
    foreach ($d[$surum]['tablolar'] as $ad => $t) {
        $yas = $t['yas'] ?? [];
        $k = array_keys($yas);
        printf("  %-16s yas %s-%s  23:%s 30:%s 60:%s | %s\n", $ad, $k[0] ?? '-', end($k) ?: '-', $yas['23'] ?? '-', $yas['30'] ?? '-', $yas['60'] ?? '-',
            mb_substr((string) ($t['aciklama'] ?? ''), 0, 60));
    }
    echo '  kurallar: ', mb_substr(is_string($d[$surum]['kurallar'] ?? null) ? $d[$surum]['kurallar'] : json_encode($d[$surum]['kurallar'] ?? null, JSON_UNESCAPED_UNICODE), 0, 700), "\n";
}
