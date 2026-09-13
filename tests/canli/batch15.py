import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
AS = C['98_askerlik_isgucu']
N = {
 # İş gücü kaybında askerlik: gelecekte kalan askerlik ve 12 aylık askerlik
 '128_askerlik_isgucu_gelecek': dict(AS, DogTarF='01.03.2006'),
 '129_askerlik_isgucu_12': dict(AS, askersureF='12'),
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
            s = run(C[tag], tag='b15'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
