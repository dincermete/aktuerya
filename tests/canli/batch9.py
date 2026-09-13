import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
YP = C['43_yeni_paylasim']
def c(base, sil=(), **kw):
    d = dict(base); d.update(kw)
    for k in sil:
        d.pop(k, None)
    return d
N = {
 # "Eş ve çocuk paylarını ayır" tek ebeveynde çocuk sayısına göre: eş + 2 çocuk + ana · eş + 3 çocuk + ana · eşsiz 2 çocuk + ana
 '108_ayir_es_2cocuk_ana': c(YP, sil=('Checkbox7', 'BabaDogTarF')),
 '109_ayir_es_3cocuk_ana': c(YP, sil=('Checkbox7', 'BabaDogTarF'), Checkbox3='on', UcDogTarF='10.10.2021', UcCinsF='K'),
 '110_ayir_essiz_2cocuk_ana': c(YP, sil=('Checkbox7', 'BabaDogTarF', 'Checkboxes', 'EsDogTarF')),
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
            s = run(C[tag], tag='b9'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
