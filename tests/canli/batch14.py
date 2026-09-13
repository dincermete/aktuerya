import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
YE = C['69_yetmis_essiz']  # %70, eşsiz: c1, c2, ana, baba
ANA = ('Checkbox6', 'AnaDogTarF'); BABA = ('Checkbox7', 'BabaDogTarF')
C1 = ('Checkbox1', 'BirDogTarF', 'BirCinsF')
C3 = dict(Checkbox3='on', UcDogTarF='10.10.2021', UcCinsF='K')
def c(base, sil=(), **kw):
    d = dict(base); d.update(kw)
    for k in sil:
        d.pop(k, None)
    return d
N = {
 # Eşsiz %70 paylaştırmasında çocuk payının çocuk ve ebeveyn sayısına göre değişimi
 '123_yetmis_3cocuk': c(YE, sil=ANA + BABA, **C3),
 '124_yetmis_1cocuk_ana': c(YE, sil=BABA + C1),
 '125_yetmis_1cocuk_anababa': c(YE, sil=C1),
 '126_yetmis_3cocuk_ana': c(YE, sil=BABA, **C3),
 '127_yetmis_1cocuk': c(YE, sil=ANA + BABA + C1),
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
            s = run(C[tag], tag='b14'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
