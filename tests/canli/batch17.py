import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
YE = C['71_yetmis_es_5cocuk']  # %70: eş, 5 çocuk, ana, baba
ANA = ('Checkbox6', 'AnaDogTarF'); BABA = ('Checkbox7', 'BabaDogTarF')
C4 = ('Checkbox4', 'DortDogTarF', 'DortCinsF'); C5 = ('Checkbox5', 'BesDogTarF', 'BesCinsF')
def c(base, sil=(), **kw):
    d = dict(base); d.update(kw)
    for k in sil:
        d.pop(k, None)
    return d
N = {
 # Eşli %70 paylaştırmasında eş + çocuk toplamı %100'ü aşınca ana-baba payının davranışı
 '130_yetmis_es_3cocuk_anababa': c(YE, sil=C4 + C5),
 '131_yetmis_es_4cocuk_anababa': c(YE, sil=C5),
 '132_yetmis_es_3cocuk_ana': c(YE, sil=C4 + C5 + BABA),
 '133_yetmis_es_3cocuk': c(YE, sil=C4 + C5 + ANA + BABA),
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
            s = run(C[tag], tag='b17'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
