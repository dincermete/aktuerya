"""Avans faiz serisinde 2018–2020 arasındaki farkı bulmak için faiz başlangıcını ay ay kaydıran vakalar (fo_taban ile aynı dosya)."""
import json, os
os.chdir(os.path.dirname(os.path.abspath(__file__)))
C = json.load(open('cases/cases.json', encoding='utf-8'))
TABAN = C['fo_taban']
hedef = []
for y in (2019, 2020):
    for m in range(1, 13):
        if m in (1, 7):
            continue  # altı aylık taramada zaten var
        ad = 'fm_%d_%02d' % (y, m)
        C.setdefault(ad, dict(TABAN, faizbaslaF='01.%02d.%d' % (m, y)))
        hedef.append([ad, 'faiz', 10])
tmp = 'cases/cases.json.tmp'
json.dump(C, open(tmp, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
os.replace(tmp, 'cases/cases.json')
json.dump(hedef, open('olcum_hedef21.json', 'w', encoding='utf-8'), indent=0)
print(len(hedef), 'hedef')
