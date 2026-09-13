import json, os, time
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
C = json.load(open('cases/cases.json', encoding='utf-8'))
B = C['01_base']; CORE = {k: B[k] for k in ('DavaliKusurF','OlayTarF','DogTarF','KendiCinsF','RapTarF','MaasF')}
FAM = {k: v for k, v in B.items() if k not in CORE}
def c(base, **kw):
    d = dict(base); d.update(kw); return d
BEKAR = C['08_bekar']; ISG = C['07_isgucu']; AKT = C['02_aktuer']; TRF = C['06_trafik']; YASLI15 = C['15_askerlik']
GENC = dict(CORE, DogTarF='01.06.2008', Checkbox6='on', AnaDogTarF='10.04.1980', Checkbox7='on', BabaDogTarF='02.11.1978')
ESKI = c(B, OlayTarF='10.05.2015')
N = {
 '31_farazi_28': c(BEKAR, farazievlenmeF='28', farazibirinciF='30', faraziikinciF='32'),
 '32_farazi_25': c(BEKAR, farazievlenmeF='25', farazibirinciF='27', faraziikinciF='29'),
 '33_farazi_28_sadece_ana': dict(CORE, DogTarF='05.05.2001', Checkbox6='on', AnaDogTarF='10.04.1975', farazievlenmeF='28', farazibirinciF='30', faraziikinciF='32'),
 '34_askerlik_dus': {k: v for k, v in YASLI15.items() if k != 'CheckboxAskerDegil'},
 '35_askerlik_22_12': c({k: v for k, v in YASLI15.items() if k != 'CheckboxAskerDegil'}, askerlikyasiF='22', askersureF='12'),
 '36_agi_es_calisiyor': c(ESKI, Checkboxagi='on', Checkboxagies='on'),
 '37_aylik_baz': c(B, Checkboxaylikbaz='on'),
 '38_aylik_baz_eski': c(ESKI, Checkboxaylikbaz='on'),
 '39_gv_istisna_agisiz': c(C['29_agisiz_eski'], CheckboxGVistisna='on'),
 '40_gv_istisna_base': c(B, CheckboxAgisiz='on', CheckboxGVistisna='on'),
 '41_gecdon_gercek_aktuer': c(AKT, Checkboxgecdongercek='on'),
 '42_gecdon_gercek_aktuer_eski': c(AKT, OlayTarF='10.05.2015', Checkboxgecdongercek='on'),
 '43_yeni_paylasim': c(B, Checkboxyenipaylasim='on'),
 '44_yeni_paylasim_es_anababa': c(C['30_kusur100_cocuksuz_anababa'], Checkboxyenipaylasim='on'),
 '45_faiz_avans': c(B, RadioGroup30='Radio23'),
 '46_faiz_bas_2025': c(B, faizbaslaF='01.01.2025'),
 '47_teknik_faiz_3': c(AKT, teknikfaizF='3,00'),
 '48_tam_hayat': c(AKT, Checkboxtamhayat='on'),
 '49_tam_hayat_isgucu': c(C['27_isgucu_aktuer'], Checkboxtamhayat='on'),
 '50_cso': c(B, RadioGroup3='Radio7'),
 '51_gatt': c(B, RadioGroup3='Radio83'),
 '52_cso_aktuer': c(AKT, RadioGroup3='Radio7'),
 '53_cso_isgucu': c(ISG, RadioGroup3='Radio7'),
 '54_pmf_isgucu_aktuer': c(C['27_isgucu_aktuer'], RadioGroup3='Radio6'),
 '55_gig': c(ISG, Checkboxgig='on', giggunF='90', kazaligigF='10.000,00'),
 '56_bakici': c(ISG, Checkboxbakici='on', bakicioranF='50'),
 '57_bakici_net': c(ISG, Checkboxbakici='on', Checkboxbakicinet='on', bakicioranF='50'),
 '58_bakici_gun': c(ISG, Checkboxgig='on', giggunF='120', Checkboxbakici='on', bakicioranF='50', bakicigunF='100'),
 '59_kazali_sgk': c(ISG, KendiSGKF='5.000,00', kazaligelbaslaF='14.03.2023'),
 '60_kazali_psd': c(ISG, KendiPSDF='400.000,00'),
 '61_es_sgk': c(B, EsSGKF='5.000,00', gelbaslaF='14.03.2023'),
 '62_es_sgk_trafik': c(B, RadioGroup40='Radio25', EsSGKF='5.000,00', gelbaslaF='14.03.2023'),
 '63_trafik_police': c(TRF, tanzimtarihF='01.06.2022'),
 '64_trafik_psd': c(TRF, EsPSDF='500.000,00'),
 '65_ayim_essiz': c({k: v for k, v in B.items() if k not in ('Checkboxes', 'EsDogTarF')}, RadioGroup2='Radio4'),
 '66_ayim_es_1cocuk': dict(CORE, RadioGroup2='Radio4', Checkboxes='on', EsDogTarF='03.02.1992', Checkbox1='on', BirDogTarF='01.09.2015', BirCinsF='K'),
 '67_ayim_es_anababa': c(C['30_kusur100_cocuksuz_anababa'], RadioGroup2='Radio4'),
 '68_ayim_es_5cocuk': c(C['10_es_5cocuk'], RadioGroup2='Radio4'),
 '69_yetmis_essiz': c({k: v for k, v in B.items() if k not in ('Checkboxes', 'EsDogTarF')}, RadioGroup2='Radio5'),
 '70_yetmis_es_anababa': c(C['30_kusur100_cocuksuz_anababa'], RadioGroup2='Radio5'),
 '71_yetmis_es_5cocuk': c(C['10_es_5cocuk'], RadioGroup2='Radio5'),
 '72_destek_sonu_yaslari': c(B, erkekdessonF='20', kizdessonF='24', unidessonF='26'),
 '73_aktif_yaslari': c(B, gelirbaslaF='20', aktifdonsonF='65'),
 '74_pasif_gelir': c(B, EmMaasF='35.000,00'),
 '75_iskonto_12': c(B, iskontooranF='12'),
 '76_yetistirme_yok': GENC,
 '77_yetistirme': c(GENC, Checkboxyetistirme='on'),
 '78_yetistirme_anne': c(GENC, Checkboxyetistirme='on', CheckboxyetistirmeAnne='on'),
 '79_yetistirme_kardes': c(GENC, Checkboxyetistirme='on', kardessayF='2'),
 '80_yetistirme_gelir': c(GENC, Checkboxyetistirme='on', yetismaasF='40.000,00'),
 '81_genc_farazi': c(GENC, farazievlenmeF='28', farazibirinciF='30', faraziikinciF='32'),
 '82_isgucu_oran_trafik': c(ISG, RadioGroup40='Radio25', KendiPSDF='400.000,00'),
}
for k, v in N.items():
    C.setdefault(k, v)
json.dump(C, open('cases/cases.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
for tag in N:
    fn = f'cases/{tag}.html'
    if os.path.exists(fn) and os.path.getsize(fn) > 50000:
        continue
    for att in range(3):
        try:
            s = run(C[tag], tag='tmp'); open(fn, 'w', encoding='utf-8').write(s); print('ok', tag, len(s), flush=True); break
        except Exception as e:
            print('err', tag, e, flush=True); time.sleep(10)
    time.sleep(4)
print('BITTI')