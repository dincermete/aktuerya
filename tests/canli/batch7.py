import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
TRF = C['06_trafik']; ISG = C['07_isgucu']
def c(base, **kw):
    d = dict(base); d.update(kw); return d
N = {
 '101_trafik_police_aktuer': c(TRF, RadioGroup='Radio11', tanzimtarihF='01.06.2022'),
 '102_trafik_police_2016_aktuer': c(TRF, RadioGroup='Radio11', tanzimtarihF='01.06.2016'),
 '103_isgucu_trafik_police_bakici': c(ISG, RadioGroup40='Radio25', tanzimtarihF='01.06.2021', IsGorOranF='85', Checkboxbakici='on', Checkboxbakicinet='on', bakicioranF='0'),
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
            s = run(C[tag], tag='b7'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')