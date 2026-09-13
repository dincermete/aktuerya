"""Canlı sitenin avans (ticari) faiz oran tablosunu çıkarmak için faiz başlangıcını ay ay kaydıran vakalar ekler.
Ardışık iki başlangıç arasındaki faiz farkı, o aralıktaki oran × gün toplamını verir. Sayfa çekmez; ölçümü olcum.py yapar."""
import json, os
os.chdir(os.path.dirname(os.path.abspath(__file__)))
C = json.load(open('cases/cases.json', encoding='utf-8'))
TABAN = C['99_faiz_avans_2025']
hedef = []
for y in range(2023, 2027):
    for m in range(1, 13):
        if (y, m) < (2023, 4) or (y, m) > (2026, 9):
            continue
        ad = 'fa_%d_%02d' % (y, m)
        d = dict(TABAN); d['faizbaslaF'] = '01.%02d.%d' % (m, y)
        C.setdefault(ad, d)
        hedef.append([ad, 'faiz', 9])
tmp = 'cases/cases.json.tmp'
json.dump(C, open(tmp, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
os.replace(tmp, 'cases/cases.json')
json.dump(hedef, open('olcum_hedef11.json', 'w', encoding='utf-8'), indent=0)
print(len(hedef), 'vaka')
