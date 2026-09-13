<?php

declare(strict_types=1);

/*
 * Arayüz ve API için basit oturum koruması. Kullanıcı adı ve şifre özeti config/giris.php dosyasındadır.
 * Hesap motoru (src/) bundan bağımsızdır; başka projeye taşırken bu dosyayı kullanmak zorunlu değildir.
 */

function oturumBaslat(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('dt_oturum');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function girisYapildi(): bool
{
    oturumBaslat();

    return isset($_SESSION['kullanici']) && is_string($_SESSION['kullanici']);
}

/** Giriş yapılmamışsa giriş sayfasına yönlendirir. */
function girisGerekli(): void
{
    if (!girisYapildi()) {
        header('Location: giris.php', true, 303);
        exit;
    }
}

/** @return array{kullanici: string, sifre_ozeti: string} */
function girisAyarlari(): array
{
    return require __DIR__ . '/../config/giris.php';
}

function csrfJetonu(): string
{
    oturumBaslat();
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrfGecerli(mixed $jeton): bool
{
    return is_string($jeton) && hash_equals(csrfJetonu(), $jeton);
}

/** Kullanıcı adı ve şifreyi sabit süreli karşılaştırmayla doğrular. */
function kimlikDogrula(string $kullanici, string $sifre): bool
{
    $ayar = girisAyarlari();
    $adDogru = hash_equals($ayar['kullanici'], $kullanici);
    $sifreDogru = password_verify($sifre, $ayar['sifre_ozeti']);

    return $adDogru && $sifreDogru;
}
