import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
A = {k: v for k, v in C['15_askerlik'].items() if k != 'CheckboxAskerDegil'}
ISG = C['07_isgucu']
def c(base, **kw):
    d = dict(base); d.update(kw); return d
N = {
 '95_askerlik_21_12': c(A, askerlikyasiF='21', askersureF='12'),
 '96_askerlik_21_3': c(A, askerlikyasiF='21', askersureF='3'),
 '97_askerlik_23_6': c(A, askerlikyasiF='23', askersureF='6'),
 '98_askerlik_isgucu': c(ISG, DogTarF='01.03.2004'),
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
            s = run(C[tag], tag='b5'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')