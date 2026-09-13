<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../app/oturum.php';

use DestekTazminat\Hesap\Girdi;

girisGerekli();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$v = Girdi::VARSAYILAN;
$bugun = (new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d');
$e = static fn (mixed $x): string => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
$tr = static fn (float|int $x): string => fmod((float) $x, 1.0) === 0.0 ? (string) (int) $x : number_format((float) $x, 2, ',', '.');
$sayi = static fn (string $k): string => $e($tr($v[$k]));
$hakTanim = [];
foreach (Girdi::HAK_SAHIPLERI as $anahtar => $t) {
    $hakTanim[] = ['anahtar' => $anahtar, 'etiket' => $t['etiket'], 'tur' => $t['tur']];
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Destek Tazminatı Hesap Masası</title>
<meta name="description" content="Destekten yoksun kalma ve iş gücü kaybı tazminatı hesaplayıcı — progresif rant / aktüeryal yöntem, TRH-2010 / PMF-1931.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap">
<link rel="stylesheet" href="assets/app.css?v=2">
</head>
<body>
<header class="top">
  <div class="top-in">
    <div>
      <div class="eyebrow">TBK m.53–54 · Destekten yoksun kalma ve iş gücü kaybı</div>
      <h1>Destek Tazminatı Hesap Masası</h1>
    </div>
    <div class="actions">
      <button type="button" class="btn" id="btnSample">Örneği yükle</button>
      <button type="button" class="btn" id="btnReset">Formu sıfırla</button>
      <button type="button" class="btn primary" id="btnPrint">Raporu yazdır</button>
      <form method="post" action="cikis.php" class="cikis">
        <input type="hidden" name="csrf" value="<?= $e(csrfJetonu()) ?>">
        <button type="submit" class="btn" title="<?= $e($_SESSION['kullanici']) ?> oturumunu kapat">Çıkış</button>
      </form>
    </div>
  </div>
</header>

<main class="wrap desk">
  <form class="form" id="frm" autocomplete="off" onsubmit="return false">

    <fieldset class="block">
      <div class="bh"><h2>Hesap</h2><span class="hint">Ondalık ayırıcı virgül · Tarihler takvimden</span></div>
      <div class="bb opts">
        <div class="opt-group">
          <span class="lab">Hesap türü</span>
          <div class="seg">
            <label><input type="radio" name="mod" data-k="mod" value="destek" checked><span>Destek tazminatı</span></label>
            <label><input type="radio" name="mod" data-k="mod" value="isgucu"><span>İş gücü kaybı</span></label>
          </div>
        </div>
        <div class="opt-group">
          <span class="lab">Kaza türü</span>
          <label class="chk"><input type="radio" name="kazaTuru" data-k="kazaTuru" value="is" checked> İş kazası</label>
          <label class="chk"><input type="radio" name="kazaTuru" data-k="kazaTuru" value="trafik"> Trafik kazası (ZMSS)</label>
        </div>
        <div class="opt-group">
          <span class="lab">Hesap yöntemi</span>
          <label class="chk"><input type="radio" name="yontem" data-k="yontem" value="progresif" checked> Progresif rant</label>
          <label class="chk"><input type="radio" name="yontem" data-k="yontem" value="aktuer"> Aktüeryal yöntem</label>
          <label class="f" style="max-width:150px"><span><abbr title="Aktüeryal yöntemde her yılın payı (1 + teknik faiz)^−(n−1) ile ayrıca indirgenir">Teknik faiz %</abbr></span><input type="text" data-k="teknikFaiz" value="0"></label>
          <label class="chk"><input type="checkbox" data-k="tamHayat"> <abbr title="İş gücü kaybında dönem başı ödemeli tam hayat anüitesi: kazalının 99 yaşına kadar yaşayabileceği kabul edilir">Tam hayat anüitesi</abbr></label>
          <label class="chk"><input type="checkbox" data-k="gecmisGercekUcret"> <abbr title="Aktüeryal yöntemde geçmiş dönem rapor tarihindeki ücret yerine o dönemin ücretleriyle hesaplanır">Geçmiş dönem ücretlerine göre</abbr></label>
        </div>
        <div class="opt-group">
          <span class="lab">Yaşam tablosu</span>
          <label class="chk"><input type="radio" name="tablo" data-k="tablo" value="trh" checked> TRH-2010 (kadın-erkek)</label>
          <label class="chk"><input type="radio" name="tablo" data-k="tablo" value="pmf"> PMF-1931</label>
          <label class="chk"><input type="radio" name="tablo" data-k="tablo" value="cso"> CSO-1980 (kadın-erkek)</label>
          <label class="chk"><input type="radio" name="tablo" data-k="tablo" value="gatt"> GATT-1983</label>
        </div>
        <div class="opt-group" data-destek>
          <span class="lab">Destek paylaştırma</span>
          <label class="chk"><input type="radio" name="paylastirma" data-k="paylastirma" value="21" checked> Eş (2), çocuk, ana, baba (1)</label>
          <label class="chk"><input type="radio" name="paylastirma" data-k="paylastirma" value="ayim"> AYİM paylaştırma</label>
          <label class="chk"><input type="radio" name="paylastirma" data-k="paylastirma" value="70"> %70'i paylaştırma (SGK)</label>
        </div>
      </div>
    </fieldset>

    <fieldset class="block">
      <div class="bh"><h2>Olay ve kazalı</h2><span class="hint">Ücret 0 ise asgari ücret esas alınır</span></div>
      <div class="bb grid">
        <label class="f"><span>Kazalı adı</span><input type="text" class="txt" data-k="kazaliAdi" placeholder="Adı Soyadı"></label>
        <label class="f"><span>Davalı kusuru %</span><input type="text" data-k="kusur" value="<?= $sayi('kusur') ?>"></label>
        <label class="f"><span>Olay tarihi</span><input type="date" data-k="olayTarihi"></label>
        <label class="f"><span>Kazalı doğum tarihi</span><input type="date" data-k="dogumTarihi"></label>
        <label class="f"><span>Cinsiyet</span><select data-k="cinsiyet"><option value="E">Erkek</option><option value="K">Kadın</option></select></label>
        <label class="f"><span>Rapor / hesap tarihi</span><input type="date" data-k="raporTarihi" value="<?= $e($bugun) ?>"></label>
        <label class="f"><span>Olay tarihi net ücret (TL)</span><input type="text" data-k="ucret" value="0"></label>
        <div class="f"><span><abbr title="Asgari geçim indirimi — yalnızca 2008–2021 dönemleri için. Eş ve çocukların doğum tarihleri yazılı olmalıdır.">Eş/çocuk ilave AGİ</abbr></span>
          <label class="chk" style="padding:6px 0"><input type="checkbox" data-k="ilaveAgi"> Hesaba kat</label></div>
        <label class="f"><span><abbr title="Boş bırakılırsa olay tarihi. Olay tarihinden önce yazılırsa olay tarihi esas alınır.">Faiz başlangıcı</abbr></span><input type="date" data-k="faizBaslangic"></label>
        <label class="f"><span>Faiz türü</span><select data-k="faizTuru"><option value="yasal">Yasal faiz</option><option value="avans">Avans (ticari) faiz</option></select></label>
      </div>
    </fieldset>

    <fieldset class="block" data-destek>
      <div class="bh"><h2>Hak sahipleri</h2><span class="hint">İşaretli olmayan kişi için tazminat hesaplanmaz</span></div>
      <div class="scroll">
        <table class="heirs">
          <thead><tr>
            <th>Kişi</th><th>Hesapla</th><th>Adı</th><th>Doğum tarihi</th><th>Cins.</th>
            <th title="Yüksek öğrenim: destek sonu üniversite yaşına uzar">Yük. öğr.</th>
            <th title="SGK tarafından bağlanan aylık gelir yazılırsa PSD tariften hesaplanır ve PSD sütununun yerine geçer">SGK aylık gelir</th>
            <th title="SGK tarafından bağlanan gelirin peşin sermaye değeri (trafik kazasında sigorta ödemesi)">SGK PSD (TL)</th>
            <th title="Evlenme, ölüm vb. halde destek alma sonu">Destek sonu</th><th>Yaş · destek</th>
          </tr></thead>
          <tbody>
          <?php foreach ($hakTanim as $h):
              $k = $h['anahtar'];
              $varsayilanCins = $h['tur'] === 'anne' || $h['tur'] === 'es' ? 'K' : 'E'; ?>
            <tr id="<?= $k ?>_tr">
              <td class="who" id="<?= $k ?>_who"><?= $e($h['etiket']) ?></td>
              <td class="c"><input type="checkbox" data-h="<?= $k ?>" data-f="hesapla" aria-label="<?= $e($h['etiket']) ?> için hesapla"></td>
              <td><input type="text" class="txt w-ad" data-h="<?= $k ?>" data-f="ad" placeholder="<?= $e($h['etiket']) ?>"></td>
              <td><input type="date" class="w-dt" data-h="<?= $k ?>" data-f="dogumTarihi"></td>
              <td><select class="w-sx" data-h="<?= $k ?>" data-f="cinsiyet">
                <option value="E"<?= $varsayilanCins === 'E' ? ' selected' : '' ?>>E</option>
                <option value="K"<?= $varsayilanCins === 'K' ? ' selected' : '' ?>>K</option></select></td>
              <td class="c"><input type="checkbox" data-h="<?= $k ?>" data-f="yuksekOgrenim" aria-label="<?= $e($h['etiket']) ?> yüksek öğrenim"></td>
              <td><input type="text" class="w-psd" data-h="<?= $k ?>" data-f="sgkGelir" value="0,00"></td>
              <td><input type="text" class="w-psd" data-h="<?= $k ?>" data-f="psd" value="0,00"></td>
              <td><input type="date" class="w-dt" data-h="<?= $k ?>" data-f="destekSonu"></td>
              <td class="info" id="<?= $k ?>_info">—</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="bb grid" style="padding-top:10px">
        <label class="f"><span><abbr title="SGK gelirinin başlangıç tarihi; boşsa olay tarihi. Tarife sürümü buna göre seçilir (25.09.2012 sonrası Ek-41).">SGK gelir başlangıcı</abbr></span><input type="date" data-k="sgkBaslangic"></label>
      </div>
    </fieldset>

    <fieldset class="block" data-isgucu hidden>
      <div class="bh"><h2>İş gücü kaybı</h2><span class="hint">Sürekli ve geçici iş göremezlik, bakıcı gideri</span></div>
      <div class="bb grid">
        <label class="f"><span>İş gücü kayıp oranı %</span><input type="text" data-k="isgucuOrani" value="<?= $sayi('isgucuOrani') ?>"></label>
        <label class="f"><span>Kazalı SGK aylık gelir</span><input type="text" data-k="kazaliSgkGelir" value="0"></label>
        <label class="f"><span>Kazalı SGK gelir başlangıcı</span><input type="date" data-k="kazaliSgkBaslangic"></label>
        <label class="f"><span>Kazalı SGK PSD (TL)</span><input type="text" data-k="kazaliPsd" value="0"></label>
        <div class="f"><span>Geçici iş göremezlik</span><label class="chk" style="padding:6px 0"><input type="checkbox" data-k="geciciHesapla"> Hesapla</label></div>
        <label class="f"><span>Geçici iş göremezlik (gün)</span><input type="text" data-k="geciciGun" value="0"></label>
        <label class="f"><span><abbr title="Geçici iş göremezlik ödeneği — hesaplanan tutardan düşülür">GİÖ</abbr> (TL)</span><input type="text" data-k="gio" value="0"></label>
        <div class="f"><span>Bakıcı gideri</span><label class="chk" style="padding:6px 0"><input type="checkbox" data-k="bakiciHesapla"> Hesapla</label></div>
        <label class="f"><span><abbr title="Boş veya 0: iş gücü kaybı oranına göre (%70–79 %50, %80–89 %75, %90+ %100)">Bakıcı oranı %</abbr></span><input type="text" data-k="bakiciOrani" value="0"></label>
        <label class="f"><span><abbr title="0 bırakılırsa bakıcı gideri ömür boyu hesaplanır">Bakıcı süresi (gün)</abbr></span><input type="text" data-k="bakiciGun" value="0"></label>
        <label class="f"><span><abbr title="Trafik kazasında ZMSS poliçe tanzim tarihi. Bakıcı oranı boşsa 01.04.2020 sonrası poliçelerde yeni genel şartlar (%50 / %100) uygulanır.">Poliçe tanzim tarihi</abbr></span><input type="date" data-k="policeTarihi"></label>
        <label class="f"><span>Bakıcı ücreti</span><select data-k="bakiciNet"><option value="1">Net asgari ücret</option><option value="0">Brüt asgari ücret</option></select></label>
      </div>
    </fieldset>

    <fieldset class="block">
      <div class="bh"><h2>Hesap parametreleri</h2><span class="hint">Varsayılanlar Yargıtay uygulamasındaki kabullerdir</span></div>
      <div class="bb grid">
        <label class="f"><span>Gelir artış oranı %</span><input type="text" data-k="artis" value="<?= $sayi('artis') ?>"></label>
        <label class="f"><span>İskonto oranı %</span><input type="text" data-k="iskonto" value="<?= $sayi('iskonto') ?>"></label>
        <label class="f"><span>Aktif dönem başlama yaşı</span><input type="text" data-k="aktifBaslamaYasi" value="<?= $sayi('aktifBaslamaYasi') ?>"></label>
        <label class="f"><span>Aktif dönem bitiş yaşı</span><input type="text" data-k="aktifBitisYasi" value="<?= $sayi('aktifBitisYasi') ?>"></label>
        <label class="f"><span>Pasif dönem aylık geliri (TL)</span><input type="text" data-k="pasifGelir" value="<?= $sayi('pasifGelir') ?>"></label>
        <label class="f" data-destek><span>Destek sonu · erkek çocuk</span><input type="text" data-k="erkekDestekSonu" value="<?= $sayi('erkekDestekSonu') ?>"></label>
        <label class="f" data-destek><span>Destek sonu · kız çocuk</span><input type="text" data-k="kizDestekSonu" value="<?= $sayi('kizDestekSonu') ?>"></label>
        <label class="f" data-destek><span>Destek sonu · üniversite</span><input type="text" data-k="universiteDestekSonu" value="<?= $sayi('universiteDestekSonu') ?>"></label>
        <label class="f" data-destek><span><abbr title="Bekâr destek için farazi evlenme yaşı. Boş: erkek 28, kadın 25. 0 yazılırsa farazi evlenme uygulanmaz. Kazalı bu yaştan büyükse yaş otomatik yükseltilir.">Farazi evlenme yaşı</abbr></span><input type="text" data-k="faraziEvlenme" value="" placeholder="E 28 · K 25"></label>
        <label class="f" data-destek><span>Farazi 1. çocuk yaşı</span><input type="text" data-k="farazi1Cocuk" value="" placeholder="E 30 · K 27"></label>
        <label class="f" data-destek><span>Farazi 2. çocuk yaşı</span><input type="text" data-k="farazi2Cocuk" value="" placeholder="E 32 · K 29"></label>
        <div class="f" data-destek><span><abbr title="Kazalı 18 yaşından küçükse anne-babanın tazminatından yetiştirme gideri düşülür">Yetiştirme gideri</abbr></span>
          <label class="chk" style="padding:6px 0"><input type="checkbox" data-k="yetistirme"> Hesapla</label></div>
        <div class="f" data-destek><span><abbr title="Anne çalışmıyorsa gider yalnızca babadan düşülür">Anne çalışmıyor</abbr></span>
          <label class="chk" style="padding:6px 0"><input type="checkbox" data-k="yetistirmeAnneYok"> İşaretle</label></div>
        <label class="f" data-destek><span><abbr title="Toplam yetiştirme gideri oranı (varsayılan 5, en çok 20)">Yetiştirme gideri oranı %</abbr></span><input type="text" data-k="yetistirmeOrani" value="5"></label>
        <label class="f" data-destek><span><abbr title="0: her dönemin net asgari ücreti. Girilirse olay tarihindeki asgari ücrete oranlanır ve asgari ücretle birlikte artırılır.">Yetiştirme esas gelir</abbr></span><input type="text" data-k="yetistirmeGelir" value="0"></label>
        <div class="f"><span>Askerlik yaşı / süre (ay)</span>
          <span class="pair"><input type="text" data-k="askerlikYasi" value="<?= $sayi('askerlikYasi') ?>" aria-label="Askerlik yaşı"><input type="text" data-k="askerlikSuresi" value="<?= $sayi('askerlikSuresi') ?>" aria-label="Askerlik süresi (ay)"></span></div>
      </div>
      <div class="bb opts" style="padding-top:4px">
        <label class="chk"><input type="checkbox" data-k="gecmisRaporTarihiIle"> Geçmiş dönemi rapor tarihi ile sınırla</label>
        <label class="chk"><input type="checkbox" data-k="askerlikDus" checked> Askerlik süresini düş (erkek, askerlik yaşından küçükse)</label>
        <label class="chk"><input type="checkbox" data-k="agisiz"> AGİ'siz asgari ücret üzerinden hesapla</label>
        <label class="chk"><input type="checkbox" data-k="gelirVergisiz"> AGİ'siz hesaplamaları gelir vergisiz hesapla</label>
        <label class="chk"><input type="checkbox" data-k="aylikBaz"> Geçmiş dönem hesabı aylık maaş bazında</label>
        <label class="chk" data-destek><input type="checkbox" data-k="esCalisiyor"> AGİ — eşin ücret geliri var</label>
        <label class="chk"><input type="checkbox" data-k="bakiyeRaporTarihine"> Bakiye ömürleri rapor tarihine göre bul</label>
        <label class="chk"><input type="checkbox" data-k="yasYukariYuvarla"> Bakiye ömürde yaşları üste yuvarla</label>
        <label class="chk" data-destek><input type="checkbox" data-k="evlenmeOlayTarihine"> Evlenme ihtimali olay tarihine göre</label>
        <label class="chk" data-destek><input type="checkbox" data-k="anneBabaYarimPay"> Anne-babaya çocukların yarısı pay</label>
        <label class="chk" data-destek><input type="checkbox" data-k="anneBaba25SinirYok"> Anne-babaya %25 pay sınırı yok</label>
        <label class="chk" data-destek><input type="checkbox" data-k="anneBabaYerineCocuk"> Anne-baba yerine 6. ve 7. çocuk</label>
      </div>
    </fieldset>
  </form>

  <aside class="report" aria-live="polite">
    <div class="sheet">
      <div class="sheet-h"><h2 id="repTitle">Hesap özeti</h2><span class="mono" id="repDate"></span></div>
      <div id="repBody"><div class="err">Hesaplanıyor…</div></div>
    </div>
  </aside>
</main>

<section class="wrap detail">
  <details class="block" open>
    <summary><h2>Paylaşım tablosu</h2><span class="hint">Destek sürelerine göre dönem dönem paylar</span></summary>
    <div class="tbl-wrap" id="tblShare"></div>
  </details>
  <details class="block">
    <summary><h2>Geçmiş dönem tablosu</h2><span class="mono hint" id="sumG"></span></summary>
    <div class="tbl-wrap" id="tblG"></div>
  </details>
  <details class="block">
    <summary><h2>Gelecek aktif dönem tablosu</h2><span class="mono hint" id="sumA"></span></summary>
    <div class="tbl-wrap" id="tblA"></div>
  </details>
  <details class="block">
    <summary><h2>Gelecek pasif dönem tablosu</h2><span class="mono hint" id="sumP"></span></summary>
    <div class="tbl-wrap" id="tblP"></div>
  </details>
  <details class="block" open>
    <summary><h2>Formüller ve kabuller</h2><span class="hint">Hesabın adım adım mantığı</span></summary>
    <div class="formula">
      <section>
        <h3>1 · Bakiye ömür ve dönemler</h3>
        <p>Kazalının olay tarihindeki tam yaşı x ile yaşam tablosundan bakiye ömür e(x) okunur (TRH-2010'da cinsiyete göre ayrı).</p>
        <code>Ömür sonu    = Olay tarihi + e(x) yıl
Geçmiş dönem = Olay → rapor yılının 31.12'si
Aktif dönem  = Geçmiş sonu → 60. yaş
Pasif dönem  = 60. yaş → ömür sonu</code>
        <p>18 yaşından önce gelir yoktur. Seçilirse askerlik süresinde gelir sıfırlanır.</p>
      </section>
      <section>
        <h3>2 · Geçmiş dönem</h3>
        <p>Olaydan hesap tarihine kadar her asgari ücret dönemi gün bazında hesaplanır. Ücret girilmişse asgari ücretle aynı oranda arttığı kabul edilir.</p>
        <code>k         = Olay ücreti ÷ Olay tarihi net asgari ücret
Aylık     = Net asgari (AGİ'li) × k + ilave AGİ
Dönem     = Aylık ÷ 30 × gün
İlave AGİ = Brüt × %15 × (eş %10 + 1.–2. çocuk %7,5 + diğer %5), en çok %35</code>
      </section>
      <section>
        <h3>3 · Gelecek dönem: progresif rant</h3>
        <p>Aktif ve pasif dönem tam aylara bölünür; her 12 ay bir yıl satırıdır. Son satır 12 aydan kısa olabilir. Pasif dönemin satır numarası aktif dönemin tam yıl sayısından devam eder.</p>
        <code>Aktif ay  = ⌊(60. yaş − geçmiş sonu) gün × 12 ÷ 365,25⌋
Pasif ay  = ⌊(e + yaş₃₆₅ − 60) × 12 + 0,05⌋   (yaş₃₆₅ = gün ÷ 365)
Satır n   = Aylık × ay sayısı × (1 + a)ⁿ ÷ (1 + i)ⁿ
a = i ise → Aylık × toplam ay</code>
        <p>Pasif dönemde gelir vergi kesilmiş net asgari ücrettir (girilen tutar daha yüksekse o).</p>
      </section>
      <section>
        <h3>4 · Aktüeryal yöntem</h3>
        <p>Geçmiş ve gelecek dönem rapor tarihindeki ücretle hesaplanır. Her yıl satırındaki pay, destek alanın hayatta kalma olasılığıyla (TRH-2010 l(x) tablosu) ve varsa teknik faizle çarpılır.</p>
        <code>Pay(n) = Satır geliri × Destek oranı × l(y+m) ÷ l(y) × (1 + t)^−(n−1)
m      = n − 1 (aktif) · aktifte kısmi satır varsa n (pasif)
y      = rapor tarihindeki yaş (yuvarlanmış)
l(x)   = bakiye ömürden: p(x) = (e(x) − ½) ÷ (e(x+1) + ½)
Pasif gelir ≥ Aktif gelirin %70'i</code>
      </section>
      <section>
        <h3>5 · Destek payları ve süreleri</h3>
        <p>Kazalıya 2, eşe 2, her çocuğa ve ana-babaya 1 pay ayrılır. Destekten çıkanın payı kalanlara dağılır. Eş veya çocuk paylaşımdayken anne-babanın toplam payı %25'i geçerse ana-babaya %25, kalan %75 kazalı, eş ve çocuklar arasında 2/2/1 oranıyla bölünür.</p>
        <code>Kişi payı      = kişinin payı ÷ (2 + Σ paylar)
Çocuk destek   = sınır yaş (18 / 22 / 25) − olay tarihindeki tam yaş
Anne, baba     = kendi bakiye ömrü, yıla yuvarlanır
Eş             = kazalının ömrüyle sınırlıysa olay → ömür sonu takvim yılı (+1, 6 ay ve üstü)
Kalan yıl      = destek yılı − geçmiş yıl (yarımda aşağı); kişi n ≤ kalan satırlarda pay alır
Satır no       = aktif ve pasif boyunca kesintisiz (kısmi satır dahil)
Eş (kazalıyla sınırlı) = kalan yıla takılmaz, ömür sonuna kadar pay alır
%70 yöntemi    = eş %50 (çocuksuz %75), çocuk %25; ana-baba varsa toplam %25 alır
                 (herkese %25'er verip %100'e sığdırınca daha az kalıyorsa o kadar),
                 eş ve çocuklar kalana orantılı sığdırılır; sonuç × %70.
                 Eş yoksa kişi payı 100·(N+1) ÷ (N·(N+2)), N = çocuk + ana-baba
Yarım pay      = anne ve baba birlikte bir çocuk payı alır
Payları ayır   = ana-baba toplamı olay tarihindeki eş ve çocuk sayısıyla, iki ebeveyn
                 sayılarak sabitlenir: 2 ÷ (2 + 2·eş + çocuk + 2); sağ kalan ebeveyn tamamını alır.
                 Satırdaki standart 2/1'de ana-baba %25'i aşarsa %25 sınırı uygulanır
AYİM           = eş yoksa eşin payının yarısı kişilere eşit dağılır; yalnız ana-baba kalırsa
                 %50 aralarında bölünür; eş/çocukla birlikte ana-baba %25'i aşarsa fazlası
                 kazalı (2), eş (2) ve çocuklar (1) arasında bölünür</code>
        <p>Geçmiş dönemde paylar her asgari ücret döneminin başındaki duruma göre verilir. Olay tarihinden 300 günden fazla sonra doğan çocuk destek hesabına katılmaz.</p>
      </section>
      <section>
        <h3>6 · İndirimler ve sonuç</h3>
        <p>Dul eşin evlenme ihtimali AYİM tablosundan okunur, destek alan her çocuk için 5 puan düşülür ve yalnızca gelecek dönem payına uygulanır.</p>
        <code>AYİM (kadın): 17–20 %52 · 21–25 %40 · 26–30 %27
31–35 %17 · 36–40 %9 · 41–50 %2 · 51–55 %1

Tazminat = (Geçmiş + Aktif + Pasif) × Kusur
         − Eş(Aktif + Pasif) × Kusur × Evlenme
         − PSD × Kusur   (iş kazası) · PSD × (1 + yasal faiz olay→rapor)   (trafik)
         − Yetiştirme gideri (ana-baba; kusurla çarpılmaz)

Yetiştirme = Σ 18 yaş dönemine kadar aylık asgari ücret × oran
             (geçmiş: tam ay × aylık + gün × aylık ÷ 30 · gelecek: satır progresyonu)
Askerlik   = askerlik yaşının dolduğu yılın 1 Ocak'ından; geçmişte ay × 30 + 5 gün, gelecekte tam ay
İşlemiş faiz = Tazminat × Σ oran × gün ÷ 365 (oran değişiminden önceki son gün sayılmaz)
Avans faizi  = 3095 m.2/2 altı aylık avans oranı (canlı karşılaştırma sitesinin serisi)

İş gücü kaybı ek kalemleri
GİG     = (gün + 1) günün geliri (aylık ÷ 30) − GİÖ; sürekli kayıptan da (gün + 1) gün çıkar
Bakıcı  = geçmişte tam ay × aylık + gün × aylık ÷ 30, gelecekte ay × progresyon; × bakıcı oranı</code>
      </section>
    </div>
    <ul class="caveat">
      <li><strong>SGK PSD:</strong> SGK aylık geliri girilirse PSD, Ek-41 (25.09.2012 sonrası) veya Ek-27 tarifesinden otomatik bulunur; elle yazılan PSD tutarı da düşülür.</li>
      <li><strong>AYİM paylaştırma:</strong> Resmi cetvelin tamamı yayımlanmış değil; kurallar karşılaştırma sitesinden ölçülerek çıkarıldı.</li>
      <li>Hesap bilirkişi raporunun yerini tutmaz; sonuçları dosyadaki verilerle kontrol edin.</li>
    </ul>
  </details>
</section>

<footer>Kaynak tablolar: TRH-2010 ve PMF-1931, 2005–2026 asgari ücretler, AYİM yeniden evlenme olasılığı tablosu. Form girişleri yalnızca bu tarayıcıda saklanır.</footer>

<script type="application/json" id="hakTanim"><?= json_encode($hakTanim, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="assets/app.js?v=2"></script>
</body>
</html>
