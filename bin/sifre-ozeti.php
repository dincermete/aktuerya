<?php

declare(strict_types=1);

/*
 * Giriş şifresinin özetini üretir; çıktıyı config/giris.php içindeki 'sifre_ozeti' alanına yazın.
 *   ddev exec php bin/sifre-ozeti.php 'yeni-sifre'
 */
if (!isset($argv[1]) || $argv[1] === '') {
    fwrite(STDERR, "Kullanım: php bin/sifre-ozeti.php <şifre>\n");
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT), "\n";
