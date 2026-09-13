import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
YP = C['43_yeni_paylasim']; YG = C['88_yetistirme_oran20']
def c(base, sil=(), **kw):
    d = dict(base); d.update(kw)
    for k in sil:
        d.pop(k, None)
    return d
N = {
 # eş + 3 çocuk + ana-baba: standart 2/1'de ana-baba toplamı %22 (< %25) → ayırma kuralının iki adayını ayırt eder
 '104_ayir_3cocuk_anababa': c(YP, Checkbox3='on', UcDogTarF='10.10.2021', UcCinsF='K'),
 '105_yetistirme_gelir': c(YG, yetismaasF='30.000,00'),
 '106_yetistirme_genc': c(YG, DogTarF='01.06.2014'),
 '107_yetistirme_tek_baba': c(YG, sil=('Checkbox6', 'AnaDogTarF')),
}
for k, v in N.items():
    C.setdefault(k, v)
tmp = 'cases/cases.json.tmp'
json.dump(C, open(tmp, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
os.replace(tmp, 'cases/cases.json')
for tag in N:
    fn = f'cases/{tag}.html'
    if os.path.exists(fn) and os.path.getsize(fn) > 50000:
        continue
    for att in range(3):
        try:
            s = run(C[tag], tag='b8'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
