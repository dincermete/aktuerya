import json,time,os,sys
os.chdir(os.path.dirname(os.path.abspath(__file__)))
from live import run
B={'DavaliKusurF':'80','OlayTarF':'14.03.2023','DogTarF':'12.06.1989','KendiCinsF':'E','RapTarF':'13.09.2026','MaasF':'0',
   'Checkboxes':'on','EsDogTarF':'03.02.1992','Checkbox1':'on','BirDogTarF':'01.09.2015','BirCinsF':'K',
   'Checkbox2':'on','IkiDogTarF':'20.05.2019','IkiCinsF':'E','YukCheckbox2':'on',
   'Checkbox6':'on','AnaDogTarF':'10.04.1963','Checkbox7':'on','BabaDogTarF':'02.11.1960'}
FAM={k:B[k] for k in B if k not in('DavaliKusurF','OlayTarF','DogTarF','KendiCinsF','RapTarF','MaasF')}
CORE={k:B[k] for k in('DavaliKusurF','OlayTarF','DogTarF','KendiCinsF','RapTarF','MaasF')}
def c(**kw): d=dict(B); d.update(kw); return d
C={}
C['01_base']=B
C['02_aktuer']=c(RadioGroup='Radio11')
C['03_pmf']=c(RadioGroup3='Radio6')
C['04_ayim']=c(RadioGroup2='Radio4')
C['05_yetmis']=c(RadioGroup2='Radio5')
C['06_trafik']=c(RadioGroup40='Radio25')
C['07_isgucu']=dict(CORE,RadioGroup4='Radio9',IsGorOranF='60')
C['08_bekar']=dict(CORE,DogTarF='05.05.2001',Checkbox6='on',AnaDogTarF='10.04.1975',Checkbox7='on',BabaDogTarF='02.11.1972')
C['09_sadece_es']=dict(CORE,Checkboxes='on',EsDogTarF='03.02.1992')
C['10_es_5cocuk']=c(Checkbox3='on',UcDogTarF='01.01.2012',UcCinsF='E',Checkbox4='on',DortDogTarF='01.01.2010',DortCinsF='K',Checkbox5='on',BesDogTarF='01.01.2021',BesCinsF='E')
C['11_yuksek_ucret']=c(MaasF='45.000,00')
C['12_eski_agi']=c(OlayTarF='10.05.2015',Checkboxagi='on')
C['13_eski_2019']=c(OlayTarF='20.08.2019')
C['14_gecdon_raptar']=c(Checkboxgecdonraptar='on')
C['15_askerlik']=dict(CORE,DogTarF='01.03.2004',CheckboxAskerDegil='on',Checkbox6='on',AnaDogTarF='10.04.1978',Checkbox7='on',BabaDogTarF='02.11.1975')
C['16_anababa25yok']=c(Checkboxanababa25yok='on')
C['17_anababayari']=c(Checkboxanababayari='on')
C['18_evl_olay']=c(Checkboxihtimal='on')
C['19_raptar_omur']=c(Checkboxraptarhesapla='on')
C['20_yas_yuvarla']=c(Checkboxyasyuvarla='on')
C['21_artis12']=c(artisoranF='12')
C['22_psd_es']=c(EsPSDF='500.000,00')
C['23_kadin_kazali']=c(KendiCinsF='K',DogTarF='12.06.1991',EsDogTarF='03.02.1987')
C['24_es_destek_sonu']=c(EsDesTarF='01.01.2030')
C['25_yasli_kazali']=dict(CORE,DogTarF='01.01.1961',Checkboxes='on',EsDogTarF='01.01.1965')
C['26_aktuer_pmf']=c(RadioGroup='Radio11',RadioGroup3='Radio6')
C['27_isgucu_aktuer']=dict(CORE,RadioGroup4='Radio9',IsGorOranF='60',RadioGroup='Radio11')
C['28_67cocuk']=c(RadioGroup20='Radio21',AnaCinsF='K',BabaCinsF='E',AnaDogTarF='01.01.2008',BabaDogTarF='01.01.2006')
C['29_agisiz_eski']=c(OlayTarF='10.05.2015',CheckboxAgisiz='on')
C['30_kusur100_cocuksuz_anababa']=dict(CORE,DavaliKusurF='100',Checkboxes='on',EsDogTarF='03.02.1992',Checkbox6='on',AnaDogTarF='10.04.1963',Checkbox7='on',BabaDogTarF='02.11.1960')
json.dump(C,open('cases/cases.json','w',encoding='utf-8'),ensure_ascii=False,indent=1)
for tag,ov in C.items():
    fn=f'cases/{tag}.html'
    if os.path.exists(fn) and os.path.getsize(fn)>50000: continue
    for att in range(3):
        try:
            s=run(ov,tag='tmp'); open(fn,'w',encoding='utf-8').write(s); print('ok',tag,len(s),flush=True); break
        except Exception as e:
            print('err',tag,e,flush=True); time.sleep(10)
    time.sleep(4)
print('BITTI')
