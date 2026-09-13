"""Avans faiz tablosunun 2005–2023 kısmı: olayı 2005'e çekilmiş vakada faiz başlangıcını altı ayda bir kaydırır.
Ardışık başlangıçlar arasındaki faiz farkı ÷ toplam tazminat, o dönemdeki oran × gün toplamını verir. Sayfa çekmez."""
import json, os
os.chdir(os.path.dirname(os.path.abspath(__file__)))
C = json.load(open('cases/cases.json', encoding='utf-8'))
TABAN = dict(C['99_faiz_avans_2025'])
TABAN['OlayTarF'] = '01.01.2005'
TABAN['DogTarF'] = '12.06.1975'
for k in ('EsDogTarF', 'BirDogTarF', 'IkiDogTarF', 'AnaDogTarF', 'BabaDogTarF'):
    TABAN.pop(k, None)
for k in ('Checkboxes', 'Checkbox1', 'BirCinsF', 'Checkbox2', 'IkiCinsF', 'YukCheckbox2', 'Checkbox6', 'Checkbox7'):
    TABAN.pop(k, None)
TABAN['Checkboxes'] = 'on'
TABAN['EsDogTarF'] = '03.02.1978'
hedef = []
C.setdefault('fo_taban', dict(TABAN, faizbaslaF='01.01.2005'))
hedef.append(['fo_taban', 'toplam', 12])
for y in range(2005, 2024):
    for m in (1, 7):
        ad = 'fo_%d_%02d' % (y, m)
        C.setdefault(ad, dict(TABAN, faizbaslaF='01.%02d.%d' % (m, y)))
        hedef.append([ad, 'faiz', 10])
tmp = 'cases/cases.json.tmp'
json.dump(C, open(tmp, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
os.replace(tmp, 'cases/cases.json')
json.dump(hedef, open('olcum_hedef17.json', 'w', encoding='utf-8'), indent=0)
print(len(hedef), 'hedef')
