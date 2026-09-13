<?php

declare(strict_types=1);

/*
 * Arayüz giriş bilgileri. Gerçek değerler depoya girmez:
 *   1) config/giris.local.php (git'e eklenmez; örnek: config/giris.local.example.php) veya
 *   2) ortam değişkenleri GIRIS_KULLANICI ve GIRIS_SIFRE_OZETI
 * Şifre özeti üretmek için: ddev exec php bin/sifre-ozeti.php 'yeni-sifre'
 */
$yerel = __DIR__ . '/giris.local.php';
if (is_file($yerel)) {
    return require $yerel;
}

$kullanici = getenv('GIRIS_KULLANICI');
$ozet = getenv('GIRIS_SIFRE_OZETI');
if (!is_string($kullanici) || $kullanici === '' || !is_string($ozet) || $ozet === '') {
    throw new RuntimeException('Giriş bilgileri tanımlı değil: config/giris.local.php oluşturun veya GIRIS_KULLANICI / GIRIS_SIFRE_OZETI ortam değişkenlerini verin.');
}

return ['kullanici' => $kullanici, 'sifre_ozeti' => $ozet];
