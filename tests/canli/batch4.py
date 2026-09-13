import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
B = C['01_base']; ISG = C['07_isgucu']
CORE = {k: B[k] for k in ('DavaliKusurF','OlayTarF','DogTarF','KendiCinsF','RapTarF','MaasF')}
GENC = C['76_yetistirme_yok']
def c(base, **kw):
    d = dict(base); d.update(kw); return d
N = {
 '83_es_sgk_tamsayi': c(B, EsSGKF='5000', gelbaslaF='14.03.2023'),
 '84_es_sgk_2024': c(B, EsSGKF='5.000,00', gelbaslaF='01.01.2024'),
 '85_es_sgk_eski': c(B, OlayTarF='10.05.2015', EsSGKF='500,00', gelbaslaF='10.05.2015'),
 '86_kazali_sgk_tamsayi': c(ISG, KendiSGKF='5000', kazaligelbaslaF='14.03.2023'),
 '87_yetistirme_oran5': c(GENC, Checkboxyetistirme='on', kardessayF='5'),
 '88_yetistirme_oran20': c(GENC, Checkboxyetistirme='on', kardessayF='20'),
 '89_yetistirme_oran20_anne': c(GENC, Checkboxyetistirme='on', CheckboxyetistirmeAnne='on', kardessayF='20'),
 '90_farazi_28_yasli_bekar': dict(CORE, DogTarF='01.02.1990', Checkbox6='on', AnaDogTarF='10.04.1965', Checkbox7='on', BabaDogTarF='02.11.1962', farazievlenmeF='28', farazibirinciF='30', faraziikinciF='32'),
 '91_bakici_oransiz_85': c(ISG, IsGorOranF='85', Checkboxbakici='on', Checkboxbakicinet='on', bakicioranF='0'),
 '92_gig_aylik_baz': c(ISG, Checkboxgig='on', giggunF='90', kazaligigF='10.000,00', Checkboxaylikbaz='on'),
 '93_faiz_bas_olay': c(B, faizbaslaF='14.03.2023'),
 '94_kadin_bekar_farazi': dict(CORE, KendiCinsF='K', DogTarF='05.05.2001', Checkbox6='on', AnaDogTarF='10.04.1975', Checkbox7='on', BabaDogTarF='02.11.1972', farazievlenmeF='25', farazibirinciF='27', faraziikinciF='29'),
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
            s = run(C[tag], tag='b4'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(5)
print('BITTI')