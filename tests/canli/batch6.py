import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
B = C['01_base']
def c(base, **kw):
    d = dict(base); d.update(kw); return d
N = {
 '99_faiz_avans_2025': c(B, RadioGroup30='Radio23', faizbaslaF='01.01.2025'),
 '100_faiz_avans_2024': c(B, RadioGroup30='Radio23', faizbaslaF='01.01.2024'),
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
            s = run(C[tag], tag='b6'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
hedef = [['99_faiz_avans_2025', 'faiz'], ['100_faiz_avans_2024', 'faiz']]
json.dump(hedef, open('olcum_hedef6.json', 'w', encoding='utf-8'))
print('BITTI')