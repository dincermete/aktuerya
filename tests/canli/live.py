import re,html,json,sys,urllib.parse,urllib.request,http.cookiejar,time
URL='https://www.ercanerdem.av.tr/destazhes_beta.asp'
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128 Safari/537.36'
fields=json.load(open('fields.json'))
def base():
    d=[];seen=set()
    for f in fields:
        if f['n'] in ('Giris','destekkullaniciF','desteksifreF','Checkboxhatirla'): continue
        if f['t'] in('radio','checkbox'):
            if f['chk']: d.append([f['n'],f['v']])
        elif f['t']=='submit':
            if f['n']=='Gonder': d.append([f['n'],f['v']])
        else: d.append([f['n'],f['v']])
    return d
def run(over,unset=(),tag='x'):
    d=base()
    names=[k for k,_ in d]
    for k,v in over.items():
        grp=[f for f in fields if f['n']==k and f['t']=='radio']
        d=[x for x in d if x[0]!=k]
        if v is not None: d.append([k,v])
    for k in unset: d=[x for x in d if x[0]!=k]
    cj=http.cookiejar.CookieJar();op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    op.addheaders=[('User-Agent',UA),('Referer',URL)]
    op.open(URL,timeout=60).read()
    body=urllib.parse.urlencode([tuple(x) for x in d],encoding='cp1254',errors='replace').encode()
    r=op.open(URL,data=body,timeout=90).read()
    open('r_'+tag+'.html','wb').write(r)
    return r.decode('cp1254','replace')
def text(s):
    s=re.sub(r'(?is)<(script|style).*?</\1>','',s)
    s=re.sub(r'(?i)<br\s*/?>|</tr>|</p>|</div>','\n',s); s=re.sub(r'<[^>]+>',' ',s); s=html.unescape(s)
    s=re.sub(r'[ \t\r]+',' ',s); return re.sub(r'\n\s*\n+','\n',s)
if __name__=='__main__':
    ov={'DavaliKusurF':'80','OlayTarF':'14.03.2023','DogTarF':'12.06.1989','KendiCinsF':'E','RapTarF':'13.09.2026','MaasF':'0',
        'Checkboxes':'on','EsDogTarF':'03.02.1992','Checkbox1':'on','BirDogTarF':'01.09.2015','BirCinsF':'K',
        'Checkbox2':'on','IkiDogTarF':'20.05.2019','IkiCinsF':'E','YukCheckbox2':'on',
        'Checkbox6':'on','AnaDogTarF':'10.04.1963','Checkbox7':'on','BabaDogTarF':'02.11.1960'}
    s=run(ov,tag='s1')
    t=text(s)
    i=t.find('KULLANILAN PARAMETRELER')
    open('s1.t','w',encoding='utf-8').write(t)
    print(len(s))
