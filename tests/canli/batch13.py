import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
GIG = C['55_gig']
N = {
 # Uzun geçici iş göremezlik: GİG satırı 250 bin'in üstüne çıksın ki satırın kendisi iki farklı sınırda ölçülebilsin
 '121_gig_uzun': dict(GIG, IsGorOranF='10', giggunF='1270', kazaligigF='0,00'),
 '122_gig_uzun_gio': dict(GIG, IsGorOranF='10', giggunF='1270', kazaligigF='100.000,00'),
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
            s = run(C[tag], tag='b13'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')
