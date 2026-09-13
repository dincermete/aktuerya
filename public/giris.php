<?php

declare(strict_types=1);

require __DIR__ . '/../app/oturum.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

if (girisYapildi()) {
    header('Location: index.php', true, 303);
    exit;
}

$hata = '';
$kullanici = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $kullanici = trim((string) ($_POST['kullanici'] ?? ''));
    $sifre = (string) ($_POST['sifre'] ?? '');
    $bekleme = (int) ($_SESSION['giris_bekle'] ?? 0);
    if (!csrfGecerli($_POST['csrf'] ?? null)) {
        $hata = 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.';
    } elseif ($bekleme > time()) {
        $hata = 'Çok fazla hatalı deneme. ' . ($bekleme - time()) . ' saniye sonra tekrar deneyin.';
    } elseif (kimlikDogrula($kullanici, $sifre)) {
        session_regenerate_id(true);
        unset($_SESSION['giris_hata'], $_SESSION['giris_bekle']);
        $_SESSION['kullanici'] = $kullanici;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: index.php', true, 303);
        exit;
    } else {
        // Hatalı denemeyi yavaşlat; 5 hatadan sonra 60 saniye bekletir
        usleep(700000);
        $_SESSION['giris_hata'] = (int) ($_SESSION['giris_hata'] ?? 0) + 1;
        if ($_SESSION['giris_hata'] >= 5) {
            $_SESSION['giris_bekle'] = time() + 60;
            $_SESSION['giris_hata'] = 0;
        }
        $hata = 'Kullanıcı adı veya şifre hatalı.';
    }
}
$e = static fn (string $x): string => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Giriş · Destek Tazminatı Hesap Masası</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap">
<link rel="stylesheet" href="assets/app.css?v=2"></head>
<body class="giris-sayfa">
<main class="giris">
  <div class="giris-kart">
    <div class="eyebrow">TBK m.53–54 · Destekten yoksun kalma ve iş gücü kaybı</div>
    <h1>Destek Tazminatı Hesap Masası</h1>
    <p class="giris-not">Devam etmek için giriş yapın.</p>
    <?php if ($hata !== ''): ?>
      <div class="err" role="alert"><?= $e($hata) ?></div>
    <?php endif; ?>
    <form method="post" action="giris.php" class="giris-form">
      <input type="hidden" name="csrf" value="<?= $e(csrfJetonu()) ?>">
      <label class="f"><span>Kullanıcı adı</span>
        <input type="text" name="kullanici" class="txt" value="<?= $e($kullanici) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus></label>
      <label class="f"><span>Şifre</span>
        <input type="password" name="sifre" class="txt" autocomplete="current-password" required></label>      <button type="submit" class="btn primary">Giriş yap</button>
    </form>
  </div>
</main>
</body>
</html>
