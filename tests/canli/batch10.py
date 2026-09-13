import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
AY = C['65_ayim_essiz']; YE = C['69_yetmis_essiz']; AYR = C['110_ayir_essiz_2cocuk_ana']
ANA = ('Checkbox6', 'AnaDogTarF'); BABA = ('Checkbox7', 'BabaDogTarF')
C1 = ('Checkbox1', 'BirDogTarF', 'BirCinsF'); C2 = ('Checkbox2', 'IkiDogTarF', 'IkiCinsF', 'YukCheckbox2')
def c(base, sil=(), **kw):
    d = dict(base); d.update(kw)
    for k in sil:
        d.pop(k, None)
    return d
N = {
 # Eşsiz AYİM ve %70 paylaştırmasını kişi kümesine göre ayrıştırmak için
 '111_ayim_2cocuk': c(AY, sil=ANA + BABA),
 '112_ayim_1cocuk': c(AY, sil=ANA + BABA + C1),
 '113_ayim_2cocuk_ana': c(AY, sil=BABA),
 '114_ayim_anababa': c(AY, sil=C1 + C2),
 '115_yetmis_2cocuk': c(YE, sil=ANA + BABA),
 '116_yetmis_2cocuk_ana': c(YE, sil=BABA),
 '117_ayir_essiz_2cocuk_anababa': c(AYR, Checkbox7='on', BabaDogTarF='02.11.1960'),
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
            s = run(C[tag], tag='b10'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
