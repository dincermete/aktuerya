import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
GIG = C['55_gig']
N = {
 # Geçici iş göremezlik: GİÖ'süz ve farklı GİÖ ile toplam farkından GİG tutarını çıkarmak için
 '118_gig_gio_yok': dict(GIG, kazaligigF='0,00'),
 '119_gig_gio_5000': dict(GIG, kazaligigF='5.000,00'),
 '120_gig_365_gun': dict(GIG, giggunF='365', kazaligigF='0,00'),
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
            s = run(C[tag], tag='b12'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
