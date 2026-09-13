<?php

declare(strict_types=1);

/*
 * Composer olmadan da çalışması için basit PSR-4 yükleyici.
 * Motoru başka bir projeye taşırken src/ klasörünü kopyalayıp bu dosyayı require etmek yeterli
 * (ya da composer.json'daki autoload satırını hedef projeye ekleyin).
 */
spl_autoload_register(static function (string $sinif): void {
    $onek = 'DestekTazminat\\';
    if (!str_starts_with($sinif, $onek)) {
        return;
    }
    $dosya = __DIR__ . '/' . str_replace('\\', '/', substr($sinif, strlen($onek))) . '.php';
    if (is_file($dosya)) {
        require $dosya;
    }
});
