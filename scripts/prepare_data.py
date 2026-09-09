import json, pathlib, openpyxl, hashlib, collections
p=pathlib.Path('database/imports')
d=json.loads((p/'workbook.json').read_text(encoding='utf8'))
a=json.loads((p/'workbook-audit.json').read_text(encoding='utf8'))
cells={k:v for r in a['Dieta'] for k,v in r.items()}
d['profile']={key:cells.get(cell) for key,cell in {'name':'C3','sex':'C5','age':'G5','weight':'J5','height':'M5','activity':'K7','goal':'C7','adjustment':'D11'}.items()}
d['meals']=[]
for r in range(16,145,16):
    items=[]
    for n in range(r+2,r+12):
        name=cells.get(f'D{n}')
        if name and not name.startswith('='):
            food=next(f for f in d['foods'] if f['name']==name)
            items.append({'food_id':food['id'],'quantity':cells[f'I{n}']})
    d['meals'].append({'name':cells[f'D{r}'],'time':cells[f'U{r}'][:5],'items':items})
d['categories']=[r[next(iter(r))] for r in []]
d['categories']=[v for r in a['Dados'][1:17] for k,v in r.items() if k.startswith('C')]
names=['Musculação Total','UNIFESP — pesquisa de nutrientes','TACO / NEPA / Unicamp','FatSecret Brasil','Calcuworld — Harris-Benedict','TDEE Calculator','Receiteria — receitas fit','Mundo Boa Forma — receitas','Receitas Light','Natue — receitas fit']
for i,s in enumerate(d['sources']):
    s.update(id=i+1,name=names[i],kind='nutrients' if i<4 else 'calculator' if i<6 else 'recipes',status='Referência da planilha; sem importação automática',imported_count=0)
d['sources'][2].update(status='Importado do arquivo oficial',current_url='https://nepa.unicamp.br/publicacoes/',download_url='https://www.nepa.unicamp.br/wp-content/uploads/sites/27/2023/10/Taco-4a-Edicao.xlsx')
d['sources'].append({'id':11,'name':'Planilha fornecida — Alimentos','url':None,'cell':'Alimentos!B2:K1500','kind':'workbook','status':'Importado integralmente','imported_count':len(d['foods'])})
for f in d['foods']:
    f.update(source_id=11,source_key=str(f['id']),source_row=f['id']+1,raw_values={},unit='g/mL')
t=openpyxl.load_workbook(p/'taco-original.xlsx',data_only=True).worksheets[0]
category='Outros'; count=0
for row in t.iter_rows(max_col=29):
    v=[c.value for c in row]
    if isinstance(v[0],str) and v[1] is None and v[0].strip() and len(v[0])<80 and v[0].strip() not in ['Número do','Alimento']: category=v[0].strip()
    if not isinstance(v[0],(float,int)) or not isinstance(v[1],str): continue
    raw={k:v[i] for k,i in {'calories':3,'protein':5,'carbs':8,'fat':6,'fiber':9,'sodium':17}.items()}
    f={'id':10000+int(v[0]),'name':v[1].strip(),'category':category.replace(' ','_'),'base_quantity':100,'source_id':3,'source_key':str(int(v[0])),'source_row':row[0].row,'raw_values':raw,'unit':'g','notes':'TACO 4ª edição, NEPA/Unicamp, 2011. Tr = traço; NA = não aplicável. Valores ausentes preservados.'}
    f.update({k:x if isinstance(x,(int,float)) else None for k,x in raw.items()})
    d['foods'].append(f); count+=1
d['sources'][2]['imported_count']=count
d['sources'][2]['sha256']=hashlib.sha256((p/'taco-original.xlsx').read_bytes()).hexdigest()
(p/'seed.json').write_text(json.dumps(d,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'workbook':546,'taco':count,'meals':len(d['meals']),'items':sum(len(m['items']) for m in d['meals']),'missing_taco_values':sum(v is None for f in d['foods'] for k,v in f.items() if k in ['calories','protein','carbs','fat','fiber','sodium'])}))
