"""
Canlı sitenin 250 bin TL'lik aralıklarından kesin tutar ölçer.

Tutarlar kusur oranıyla doğrusal ölçeklenir: V(k) = V × k / 100.
Kusuru ikiye bölerek bir aralık sınırının (250.000 × n) tam geçildiği k bulunur ve V = sınır × 100 / k çözülür.

    python olcum.py dene                  # ondalıklı kusur kabul ediliyor mu
    python olcum.py olc 01_base kisi.baba # tek kalemi ölç
"""
import json, os, re, html, sys, time

os.chdir(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.getcwd())
from live import run  # noqa: E402

BEKLE = 3.0
ONBELLEK = 'olcum_onbellek.json'
SONUC = 'olcum_sonuc.json'
# Paralel ikinci süreç için: python olcum.py toplu hedef.json --ayri  → olcum_sonuc_<hedef>.json ve kendi önbelleği
if '--ayri' in sys.argv and len(sys.argv) > 2:
    _ek = os.path.splitext(os.path.basename(sys.argv[2]))[0]
    ONBELLEK = 'olcum_onbellek_%s.json' % _ek
    SONUC = 'olcum_sonuc_%s.json' % _ek
    BEKLE = 4.0
ANAHTARLAR = ['es', 'c1', 'c2', 'c3', 'c4', 'c5', 'ana', 'baba']


def metin(s):
    s = re.sub(r'(?is)<(script|style).*?</\1>', '', s)
    s = re.sub(r'(?i)<br\s*/?>|</tr>|</p>|</div>', '\n', s)
    s = re.sub(r'<[^>]+>', ' ', s)
    s = html.unescape(s)
    return re.sub(r'[ \t\r\xa0]+', ' ', s)


def ayristir(s):
    t = metin(s)
    r = {}
    i = t.find('S O N U Ç L A R')
    if i < 0:
        return r
    j = t.find('İşlemiş Yasal Faiz', i)
    bolum = t[i:j if j > 0 else None]
    d = bolum.find('DESTEK PAYLARINA GÖRE DAĞILIM')
    araliklar = [(m.start(), int(m.group(1).replace('.', '')), int(m.group(2).replace('.', '')))
                 for m in re.finditer(r'([\d.]+) TL - ([\d.]+) TL Arası', bolum)]
    fm = re.search(r'İşlemiş (?:Yasal|Ticari|Avans) Faiz\s*:\s*([\d.]+) TL - ([\d.]+) TL Arası', t)
    if fm:
        r['faiz'] = (int(fm.group(1).replace('.', '')), int(fm.group(2).replace('.', '')))
    if d >= 0:
        once = [a for a in araliklar if a[0] < d]
        sonra = [a for a in araliklar if a[0] > d]
        # İş gücü kaybında dağılımdan önceki tüm kalemler sırasıyla: geçmiş, aktif, pasif, [ek satırlar], toplam
        for n, a in enumerate(once):
            r['once.%d' % n] = a[1:]
        if once:
            r['once.son'] = once[-1][1:]
        for n, k in enumerate(['kazali.gecmis', 'kazali.aktif', 'kazali.pasif']):
            if n < len(once):
                r[k] = once[n][1:]
        for n, k in enumerate(ANAHTARLAR):
            if n < len(sonra):
                r['kisi.' + k] = sonra[n][1:]
        if len(sonra) > 8:
            r['toplam'] = sonra[8][1:]
    else:
        # İş gücü kaybı sayfasında dağılım bölümü yok: tüm kalemler sırasıyla geçmiş, aktif, pasif, [ek satırlar], toplam
        for n, a in enumerate(araliklar):
            r['once.%d' % n] = a[1:]
        if araliklar:
            r['once.son'] = araliklar[-1][1:]
            r['toplam'] = araliklar[-1][1:]
        for n, k in enumerate(['kazali.gecmis', 'kazali.aktif', 'kazali.pasif']):
            if len(araliklar) > 3 and n < len(araliklar):
                r[k] = araliklar[n][1:]
    m = re.search(r'name="?DavaliKusurF"?[^>]*value="?([^" >]*)', s) or re.search(r'value="?([^" >]*)"?\s+name="?DavaliKusurF', s)
    r['_kusur_yankisi'] = m.group(1) if m else None
    return r


def onbellek():
    try:
        return json.load(open(ONBELLEK, encoding='utf-8'))
    except Exception:
        return {}


def istek(ov, kalem=None):
    anahtar = json.dumps(ov, sort_keys=True, ensure_ascii=False)
    ob = onbellek()
    # Önbellekteki kayıt eski ayrıştırıcıyla yazılmış ve istenen kalemi içermiyorsa yeniden çek
    if anahtar in ob and (kalem is None or kalem in ob[anahtar]):
        return ob[anahtar]
    for deneme in range(3):
        try:
            s = run(ov, tag='olcum')
            break
        except Exception as e:  # sunucu hatası: bekle ve yeniden dene
            print('  hata:', e, flush=True)
            time.sleep(8)
    else:
        return None
    r = ayristir(s)
    ob = onbellek()
    ob[anahtar] = r
    json.dump(ob, open(ONBELLEK, 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
    time.sleep(BEKLE)
    return r


def kusur_yaz(k):
    return ('%.4f' % k).rstrip('0').rstrip('.').replace('.', ',')


def olc(vaka, kalem, adim=14, kaydir=0):
    """Kalemin %100 kusurdaki kesin değerini ölçer. Döner: (alt, üst) TL.
    kaydir > 0: %100'deki alt sınırın kaydir × 250 bin altındaki sınırı kullanır (kusurla çarpılmayan sabit kısmı ayırmak için)."""
    ov = dict(json.load(open('cases/cases.json', encoding='utf-8'))[vaka])
    ov['DavaliKusurF'] = '100'
    r = istek(ov, kalem)
    if not r or kalem not in r:
        return None
    lo, hi = r[kalem]
    lo -= kaydir * 250000
    if lo <= 0:
        # %100'de ilk aralıkta: üst sınır 250 bin; kesin değer için 250 bin sınırını geçen k aranamaz
        return (0, hi)
    sinir = lo  # V×k/100 = sinir olan k'yı ara: k∈(0,100]
    alt_k, ust_k = sinir * 100 / hi, 100.0  # V ∈ [lo, hi) → sınır k'sı bu aralıkta
    for _ in range(adim):
        orta = (alt_k + ust_k) / 2
        ov['DavaliKusurF'] = kusur_yaz(orta)
        rr = istek(ov)
        if not rr or kalem not in rr:
            return None
        if rr[kalem][0] >= sinir:
            ust_k = orta
        else:
            alt_k = orta
    return (sinir * 100 / ust_k, sinir * 100 / alt_k)


if __name__ == '__main__':
    komut = sys.argv[1] if len(sys.argv) > 1 else 'dene'
    if komut == 'dene':
        base = json.load(open('cases/cases.json', encoding='utf-8'))['01_base']
        for k in ['80', '80,5', '80.5', '3']:
            ov = dict(base, DavaliKusurF=k)
            print(k, istek(ov), flush=True)
        c26 = json.load(open('cases/cases.json', encoding='utf-8'))['26_aktuer_pmf']
        print('26', istek(c26), flush=True)
    elif komut == 'olc':
        print(sys.argv[2], sys.argv[3], olc(sys.argv[2], sys.argv[3]), flush=True)
    elif komut == 'toplu':
        # olcum_hedef.json: [["01_base", "kisi.baba"], ...]
        hedefler = json.load(open(sys.argv[2] if len(sys.argv) > 2 else 'olcum_hedef.json', encoding='utf-8'))
        sonuc = {}
        try:
            sonuc = json.load(open(SONUC, encoding='utf-8'))
        except Exception:
            pass
        for hedef in hedefler:
            vaka, kalem = hedef[0], hedef[1]
            anahtar = vaka + '|' + kalem + ('@%d' % hedef[3] if len(hedef) > 3 and hedef[3] else '')
            if anahtar in sonuc:
                continue
            v = olc(vaka, kalem, *hedef[2:])  # isteğe bağlı 3. öğe: bisection adım sayısı
            sonuc[anahtar] = v
            print(anahtar, v, flush=True)
            json.dump(sonuc, open(SONUC, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        print('BITTI', flush=True)
