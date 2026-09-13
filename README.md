# Destek Tazminatı Hesap Masası

Destekten yoksun kalma (TBK m.53) ve iş gücü kaybı (TBK m.54) tazminatı hesaplayıcı.
Hesap motoru saf PHP'dir, hiçbir çerçeveye ve veritabanına bağlı değildir. Arayüz bu motoru JSON API üzerinden kullanır.

- Yerel adres: https://destektazminat.ddev.site
- PHP 8.3, apache-fpm, veritabanı yok (`omit_containers: [db]`)

## Klasör yapısı

```
src/                      ← taşınabilir hesap motoru (tek bağımlılık: PHP ≥ 8.1)
  bootstrap.php           ← composer olmadan PSR-4 yükleyici
  Hesap/Hesaplayici.php   ← hesapla(array $girdi): array
  Hesap/Girdi.php         ← girdi alanları, varsayılanlar, tip dönüşümleri
  Hesap/Paylastirma.php   ← 2/1, AYİM, %70 destek paylaştırması
  Veri/YasamTablosu.php   ← TRH-2010, PMF-1931, l(x) türetme
  Veri/AsgariUcret.php    ← 2005–2026 brüt / net asgari ücretler
  Veri/EvlenmeIhtimali.php← AYİM yeniden evlenme olasılığı
  Destek/Tarih.php, Sayi.php
public/
  index.php               ← form ve rapor ekranı
  api.php                 ← POST JSON → sonuç JSON
  assets/app.css, app.js
bin/hesapla.php           ← komut satırı: JSON dosyası → JSON sonuç
tests/ornek.json          ← örnek girdi
tests/canli/              ← canlı siteyle karşılaştırma düzeneği
```

## Giriş

Arayüz (`index.php`) ve API (`api.php`) giriş yapmadan açılmaz: sayfa `giris.php`'ye yönlendirir, API 401 döner.
Giriş bilgileri depoya girmez: `config/giris.local.php` dosyasında (git'e eklenmez) veya `GIRIS_KULLANICI` / `GIRIS_SIFRE_OZETI` ortam değişkenlerinde durur; şifre düz metin tutulmaz. İlk kurulumda:

```bash
cp config/giris.local.example.php config/giris.local.php
ddev exec php bin/sifre-ozeti.php 'yeni-sifre'
```

ikinci komutun çıktısını `giris.local.php` içindeki `sifre_ozeti` alanına yapıştırın. Oturum çerezi HttpOnly/SameSite=Lax, formlar CSRF jetonlu; 5 hatalı denemeden sonra 60 sn bekletilir.Oturum kodu `app/oturum.php` içindedir; hesap motoru (`src/`) buna bağlı değildir. Deneme: `ddev exec bash tests/giris_testi.sh`.

## Günlük kullanım

```bash
cd ~/projects/destektazminat
ddev start
ddev exec php bin/hesapla.php tests/ornek.json
```

## Motoru başka bir projeye taşıma

**Composer ile (Laravel vb.):** `src/` klasörünü örneğin `packages/destek-tazminati/src` altına kopyalayın ve `composer.json`'a ekleyin:

```json
"autoload": { "psr-4": { "DestekTazminat\\": "packages/destek-tazminati/src/" } }
```

```php
use DestekTazminat\Hesap\Hesaplayici;

$sonuc = (new Hesaplayici())->hesapla($request->all());
return response()->json($sonuc, $sonuc['hata'] ? 422 : 200);
```

**Düz PHP:** `src/` klasörünü kopyalayıp `require 'src/bootstrap.php';` yazmanız yeterli.

**Arayüzü de taşımak için:** `public/assets/app.js` içindeki `API` sabitini yeni adrese çevirin (Laravel'de örneğin `/api/destek-tazminati`).

## Girdi

Tüm alanlar ve varsayılanları `src/Hesap/Girdi.php` içindeki `VARSAYILAN` sabitindedir. Tarihler `2023-03-14` veya `14.03.2023`, tutarlar `45000`, `45.000,00` biçiminde verilebilir.

```json
{
  "mod": "destek", "yontem": "progresif", "tablo": "trh", "paylastirma": "21",
  "kusur": 80, "olayTarihi": "14.03.2023", "dogumTarihi": "12.06.1989", "cinsiyet": "E", "raporTarihi": "13.09.2026",
  "hakSahipleri": { "es": { "hesapla": true, "dogumTarihi": "03.02.1992" } }
}
```

## Yıllık bakım

Her ocak ayında (ve ara zam olursa) `src/Veri/AsgariUcret.php` içindeki `DONEMLER` listesine yeni satır ekleyin: başlangıç tarihi, brüt, net. `Girdi::VARSAYILAN['pasifGelir']` değerini de yeni net asgari ücretle güncelleyin.

## Canlı siteyle karşılaştırma

`tests/canli/` klasörü, ercanerdem.av.tr hesaplayıcısına aynı girdileri gönderip sonuçları motorla karşılaştırır. Canlı site üye olmayanlara tutarları 250.000 TL'lik aralıklarla gösterir; süreler ve destek yılları tam değerdir.

```bash
# Windows PowerShell (Python gerektirir, istekler arasında 4 sn bekler)
python \\wsl.localhost\Ubuntu\home\meteh\projects\destektazminat\tests\canli\batch.py
# WSL
ddev exec php tests/canli/karsilastir.php        # tümü
ddev exec php tests/canli/karsilastir.php aktuer # adında "aktuer" geçen vakalar
```

Sonuç `tests/canli/rapor.md` dosyasına yazılır. Vakalar `tests/canli/batch.py` içinde tanımlıdır.
Varyant denemek için `--set=_anahtar=deger` (anahtarlar `src/Hesap/Girdi.php` sonunda), sadece özet için `--ozet`.

### Kesin ölçüm

Canlı site tutarları aralıkla gösterse de tutarlar kusur oranıyla doğrusal ölçeklenir ve form `80,5` gibi ondalık kusur kabul eder. `olcum.py` kusuru ikiye bölerek aralık sınırının geçildiği noktayı bulur. Böylece bir kalemin %100 kusurdaki kesin değeri ±20 TL hassasiyetle çıkar.

```bash
python \\wsl.localhost\Ubuntu\home\meteh\projects\destektazminat\tests\canli\olcum.py toplu olcum_hedef.json
ddev exec php tests/canli/kesin.php      # ölçülen kesin değerler ↔ motor
ddev exec php tests/canli/sureler.php    # ekrandaki sürelerin formüllerini dener
```

### Son karşılaştırma (13.09.2026, 134 vaka)

| Ölçüt | Tutan |
|---|---|
| Tutar aralıkları (kazalı geliri, kişi başı, genel toplam, işlemiş faiz) | 1074 / 1074 (%100) |
| Süreler (yaş, bakiye ömür, faal çalışma, dönemler) | 804 / 804 (%100) |
| Destek yılları | 428 / 428 (%100) |
| Kesin ölçülen kalemler (kusur %100) | 321 / 323 fark ≤ %0,04; kalan 2 kalem %0,10 |

Karşılaştırma araçları: `bash tests/canli/ozet.sh` (tutmayanlar), `bash tests/canli/coklu.sh 54_,03_ _anahtar d1 d2` (varyant), `ddev exec php tests/canli/satirlar.php <vaka>` (satır grupları ve paylar).

### Canlıdan ölçülerek çıkarılan kurallar

**Dönemler ve süreler**
- Geçmiş dönem gün bazında, her asgari ücret dönemi için ayrı hesaplanır. Paylar dönemin başındaki duruma göre verilir.
- Gelecek dönem ay bazındadır: aktif dönem takvim ayı, pasif dönem `⌊(e + yaş₃₆₅ − 60) × 12 + 0,05⌋` ay. Her 12 ay bir yıl satırıdır. Pasif satırlar aktif dönemin tam yıl sayısından devam eder.
- Ekrandaki süreler: geçmiş ve aktif `⌊gün × 12 ÷ 365,25⌋`, yaş yuvarlanır, faal çalışma `⌊(60 − yaş₃₆₅) × 12⌋`. Burada yaş₃₆₅ = gün ÷ 365.
- Ücret katsayısı 2 haneye yuvarlanır.

**Destek süreleri**
- Çocuk: sınır yaş (18/22/25) − olay tarihindeki tam yaş. Olaydan 300 günden fazla sonra doğan çocuk hesaba katılmaz.
- Anne ve baba: kendi bakiye ömrü, yıla yuvarlanır.
- Kazalının ömrüyle sınırlanan eş: olay → ömür sonu takvim yılı farkı, ay farkı 6 ve üzeriyse +1.
- Gelecekte kalan yıl = destek yılı − geçmiş yıl (yarımda aşağı).
- Yıl satırları aktif dönemden pasif döneme kesintisiz numaralanır; aktif dönemin kısmi son satırı da sayılır. Kişi `n ≤ kalan` satırlarda pay alır.
- Kazalının ömrüyle sınırlanan eş kalan yıla takılmaz, ömür sonuna kadar pay alır.
- İskonto katsayısı (Kn) ise pasif dönemde aktif dönemin tam yıl sayısından devam eder.
- Aktif dönem tutarı, günleri yok sayan ay farkıyla hesaplanır (13.09.2026 → 12.06.2049 = 273 ay).

**Paylaştırma**
- 2/1: anne-babanın toplam payı %25'i geçerse ana-babaya %25, kalan %75 kazalı/eş/çocuk arasında 2-2-1.
- "Çocukların yarısı pay": anne ve baba birlikte bir çocuk payı (ikisi varsa 0,5'er).
- %70: eş %50 (çocuksuz %75), çocuk %25; ana-baba varsa toplam %25, eş ve çocuklar kalan %75'e sığdırılır; sonuç × 0,7.
- Evlenme ihtimali: AYİM (rapor tarihindeki yaş) − 5 × olay tarihinde destek alan çocuk.

**Seçenekler**
- AGİ'siz: geçmişte yalnızca AGİ çıkarılır (2022 sonrası normal net), gelecekte vergi kesilmiş net (2026: 23.613 TL).
- Farazi evlenme varsayılanı 0 (uygulanmaz). "Yaşları üste yuvarla" en yakın tam yaşa yuvarlar.
- Askerlik düşümü varsayılan açık. Canlıdaki "Asker Değil" kutusu işaretlenince kapanır.
- "Bakiye ömürleri rapor tarihine göre" yalnızca hak sahiplerine uygulanır.
- Aktüeryal (destek ve iş gücü kaybı): aktif satır n'de `l(y+n−1) ÷ l(y)`. Pasif satırlarda, aktif dönemin kısmi satırı varsa adım bir fazladır. l(x) bakiye ömürden türetilir: `p(x) = (e(x) − ½) ÷ (e(x+1) + ½)`. y, rapor tarihindeki yaşın yuvarlanmış halidir. Teknik faiz varsa her satır ayrıca `(1 + t)^−(n−1)` ile indirgenir.
- Olay tarihinde 60 yaşını geçmiş kazalıda geçmiş dönem de o dönemin asgari ücretiyle hesaplanır.

**Karşılaştırılamayan:** Aktüeryal + PMF-1931 birleşimi canlıda HTTP 500 veriyor (vaka 26).

## Ek özellikler (canlıyla ölçülerek eklendi)

| Özellik | Kural | Doğrulama |
|---|---|---|
| İşlemiş faiz | toplam × Σ oran × gün ÷ 365; başlangıç olaydan önceyse olay tarihi | yasal %24 dönemi birebir, base +%0,03 |
| Avans (ticari) faiz | canlı sitenin serisi (`avans_canli`): 3095 m.2/2 dönemsel avans oranı (Ocak'ta 31 Aralık oranı, Temmuz'da 30 Haziran oranı 5 puan farklıysa); 29.06.2018–31.12.2020 arasında TCMB'nin gerçek değişiklik tarihleri; Haziran 2023'ten sonraki TCMB değişiklikleri yok, 01.06.2024'ten %53,25. Faiz başlangıcı 2005–2026 boyunca altı ayda bir, 2019–2020 ve 2023–2024'te ay ay kaydırılarak ölçüldü | kesin ✓ ≤ %0,02 (45, 99, 100, fo_*, fm_*, fa_*) |
| Trafik PSD | sigorta ödemesi kusurla çarpılmaz, olaydan rapor tarihine yasal faiziyle düşülür | aralık ✓ |
| SGK aylık gelirinden PSD | aylık × 12 × katsayı ÷ 100; Ek-41 (25.09.2012 sonrası) / Ek-27; yaş 6 ay yuvarlanır | sitenin örneği birebir; canlı site üye olmayan isteklerde uygulamıyor |
| CSO-1980, GATT-1983 | bakiye ömür tabloları | bakiye ay ve destek yılları birebir |
| Farazi evlenme | varsayılan erkek 28/30/32, kadın 25/27/29; kazalı büyükse yaş+2; çocuklar üniversite sonuna kadar | kesin ✓ |
| AYİM | açıklamadaki tablo (1 çocuk %45/%15 … 7 çocuk %29/%6); eş yoksa eş payının yarısı diğerlerine | aralık ✓ |
| Aylık maaş bazı | tam ay × aylık + kalan gün × aylık ÷ 30 | kesin ✓ |
| AGİ'siz + gelir vergisiz | geçmiş dönemde de vergi kesilmiş net | kesin ✓ |
| Aktüeryal + geçmiş dönem ücretleri | geçmiş dönem o dönemin ücretleriyle | aralık ✓ |
| Tam hayat anüitesi (iş gücü) | ömür 99 yaşa kadar | süreler ✓ |
| Bakıcı oranı boş | iş gücü oranına göre %50 / %75 / %100 | aralık ✓ |
| Yetiştirme gideri | kazalının gelirsiz (18 yaş dönemi öncesi) süresinde her ücret döneminin asgari ücreti × oran; geçmişte tam ay × aylık + gün × aylık ÷ 30, gelecekte yıl satırı progresyonuyla. Esas gelir girilirse olay tarihindeki asgari ücrete oranlanıp ilerletilir. Tutar ana-baba arasında eşit bölünür (anne çalışmıyorsa yalnız babadan) ve **kusur indiriminden sonra** düşülür | kesin ✓ (87, 88, 89, 105, 106, 107) |
| 18 yaş altı kazalı | 18 yaşın dolduğu ücret dönemi (geçmiş) / yıl satırı (gelecek) tamamen gelire dahil | kesin ✓ (76, 106) |
| Eş ve çocuk paylarını anne-babadan ayır | ana-baba toplam payı olay tarihindeki eş ve çocuk sayısıyla, iki ebeveyn varsayılarak 2/1'den bulunur ve sabit kalır (eş + 2 çocuk → %25, eş + 3 çocuk → 2/9, yalnız eş → 1/3); hayatta olan ebeveynler bu payı paylaşır. Satırdaki standart 2/1'de ana-baba toplamı %25'i aşarsa %25 sınırı uygulanır. Eş ve çocuklar kalanı kazalıyla 2-2-1 böler | kesin ✓ (43, 44, 104, 108, 109, 110) |
| Askerlik | askerlik yaşının dolduğu yılın 1 Ocak'ından başlar; destekte geçmiş dönemde ay × 30 + 5 gün, gelecek dönemde tam ay düşülür. İş gücü kaybında canlı site süreden bağımsız yalnızca 5 gün düşüyor, gelecekte düşmüyor | kesin ✓ (34, 35, 95–98, 128, 129) |
| Geçici iş göremezlik | GİG satırı: olay gününden itibaren (gün + 1) günün geliri (dönemin aylık ücreti ÷ 30) − GİÖ, kusurla çarpılır. Sürekli iş gücü kaybı geçmiş döneminden de (gün + 1) gün çıkarılır | kesin ✓ (55, 92, 118–122) |
| Bakıcı gideri | geçmişte her ücret döneminde tam ay × aylık + gün × aylık ÷ 30, gelecekte ay × satır progresyonu; oran boşsa iş gücü kaybı oranına göre | kesin ✓ (56, 57, 91, 103) |
| AYİM, eş yok | eşin payının yarısı kişilere eşit dağılır; yalnız ana-baba kalırsa %50 aralarında; eş/çocukla birlikte ana-baba %25'i aşarsa fazlası kazalı (2), eş (2), çocuk (1) arasında | kesin ✓ (65, 111–114) |
| %70, eş yok | kişi payı N = çocuk + ana-baba sayısıyla 100 × (N + 1) ÷ (N × (N + 2)), en çok %50; hesap %70 uygulanmış yüzdelerle ve her adımda iki haneye yukarı yuvarlanarak yapılır. Ana-baba toplamı %17,5'le (%25 × 0,7) sınırlı; çocuk payı %25'in altındaysa aşan kısım çocuklar ve kazalı (2) arasında bölünür | kesin ✓ (69, 115, 116, 123–127, 134, 135) |
| %70, eş + çok çocuk | ana-baba toplam %25; ama herkese %25'er verip %100'e sığdırınca daha az kalıyorsa o kadar (eş + 5 çocuk → %11,1'er) | kesin ✓ (05, 70, 71, 130–133) |
| Yasal faiz günleri | oran değişiminden önceki son gün hiçbir dilime katılmıyor | kesin ✓ (01, 12, 46, 93) |
| Teknik faiz (aktüeryal) | indirgeme üssü hayatta kalma adımıyla aynı (pasif satırlarda kısmi aktif satır +1) | kesin ✓ (47) |

## Bilinen sınırlar

- ZMSS teminat limitleri uygulanmıyor.
- AYİM paylaştırma cetvelinin tamamı yayımlanmış değil; kurallar canlı siteden ölçülerek çıkarıldı.
- "Eş ve çocuk paylarını ayır" modunda iki ölçümde çocuk payı %0,10 sapıyor (104 c3, 110 c2); aralıklar tutuyor.
- Canlı sitenin uygulamadığı ama motorun doğru kabul ettiği farklar (karşılaştırmada `_faizVeriSonu` / `_sgkPsdOtomatik` ile kapatılır): 31.07.2026'dan itibaren %31 yasal faiz; SGK aylık gelirinden otomatik PSD.
- Aktüeryal + PMF-1931 destek hesabı canlıda HTTP 500 verdiği için karşılaştırılamadı.
