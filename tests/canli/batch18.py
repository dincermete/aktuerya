import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
YE = C['69_yetmis_essiz']  # %70, eşsiz: c1, c2, ana, baba
C3 = dict(Checkbox3='on', UcDogTarF='10.10.2021', UcCinsF='K')
C4 = dict(Checkbox4='on', DortDogTarF='01.01.2010', DortCinsF='K')
N = {
 # Eşsiz %70: ana-baba sınıra takıldığında çocuk payı (N = 5 ve 6)
 '134_yetmis_3cocuk_anababa': dict(YE, **C3),
 '135_yetmis_4cocuk_anababa': dict(YE, **C3, **C4),
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
            s = run(C[tag], tag='b18'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
