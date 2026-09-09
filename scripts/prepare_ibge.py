"""Select additional foods from the original IBGE POF workbook extraction."""
import hashlib
import json
import re
import unicodedata
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DIRECTORY = ROOT / 'database/imports'
data = json.loads((DIRECTORY / 'ibge-raw.json').read_text(encoding='utf-8'))
original = json.loads((DIRECTORY / 'seed.json').read_text(encoding='utf-8'))


def normalize(value):
    return ' '.join(re.sub(r'[^a-z0-9]+', ' ', unicodedata.normalize('NFKD', value.lower()).encode('ascii', 'ignore').decode()).split())


def identity(value):
    value = normalize(value).replace('de vaca ', '').replace('muzarella', 'mucarela').replace('mussarela', 'mucarela')
    value = re.sub(r'\b(cru|crua|cruas|crus|in natura|de qualquer marca|de qualquer sabor|de qualquer marca ou sabor)\b', '', value)
    return ' '.join(value.split())


existing = {identity(food['name']) for food in original['foods']}
references = {int(row[0]): row[2].strip() for row in data['references'] if isinstance(row[0], (float, int))}
categories = {65: 'Cereais e farinhas', 66: 'Frutos e oleaginosas', 68: 'Frutas', 69: 'Doces e complementos', 79: 'Laticínios e alternativas vegetais', 80: 'Pães, bolos e biscoitos', 82: 'Bebidas', 85: 'Preparações'}
seen = set()
foods = []
excluded_names = {'ananas', 'baconzitos', 'leite em po integral', 'leite em po desnatado', 'leite beijinho', 'leite de vaca fresco', 'queijo muzarella', 'queijo ralado', 'requeijao', 'tofu', 'mel', 'iogurte desnatado', 'queijo de minas', 'pao integral', 'pao de milho', 'mussarela light', 'coco da bahia seco ou verde', 'banana ouro prata d agua da terra etc', 'laranja pera seleta lima da terra etc', 'limao comum galego etc', 'cuscuz'}
for line, row in enumerate(data['rows'], start=5):
    if not isinstance(row[0], (int, float)):
        continue
    group = int(row[0]) // 100000
    name = row[1].strip()
    key = normalize(name)
    if group not in categories or int(row[2]) != 99 or int(row[4]) == 2:
        continue
    signature = (normalize(str(row[5])), tuple(row[i] for i in (6, 7, 8, 9, 10, 16, 17)))
    if signature in seen:
        continue
    seen.add(signature)
    if key in excluded_names:
        continue
    if 'organic' in key or 'nao especificad' in key or identity(name) in existing:
        continue
    if key in {'castanha da india', 'caramelo bala', 'bombom caramelizado de qualquer marca diet', 'chips salgadinhos', 'chips salgadinhos light', 'presuntinho biscoito', 'suco', 'vitamina'}:
        continue
    if group == 65 and re.search(r'macarrao|miojo|pizza|pastel|sustagem|complemento alimentar', key):
        continue
    if group == 69 and not re.search(r'geleia|amendoim|barra de cereais|fruta seca|mel|beiju|pamonha|cuscuz|achocolatado|ovomaltine|rabanada', key):
        continue
    if group == 82 and not re.search(r'cafe|cha |mate|cevada|agua de coco|suco de clorofila', key):
        continue
    if group == 85 and not re.search(r'^suco|^vitamina|^sanduiche|^cafe|^pao com|mingau|mungunza|canjica|curau|omelete|gemada|acai|salada de frutas|misto quente|panqueca', key):
        continue
    if key == 'cafe da manha':
        continue
    # IBGE assigns synonyms and some light/diet variants the same reference and values.
    existing.add(identity(name))
    values = {nutrient: row[column] if isinstance(row[column], (int, float)) else None for nutrient, column in {'calories': 6, 'protein': 7, 'fat': 8, 'carbs': 9, 'fiber': 10, 'sodium': 16}.items()}
    foods.append(dict(name=name.capitalize(), category=categories[group], base_quantity=100, unit='g', **values,
        source_key=f'{int(row[0])}:{int(row[2])}', source_row=line,
        notes=f'IBGE/POF 2008–2009 (2011), por 100 g de parte comestível. Referência {int(row[4])}: {references[int(row[4])]}. Alimento na referência: {row[5]}. Sódio conforme a coluna Sódio (mg); o sódio de adição estimado é registrado separadamente na origem. Composições históricas; preparações e produtos podem variar.',
        raw_values={'ibge_food_code': int(row[0]), 'preparation_code': int(row[2]), 'preparation': row[3], 'reference_code': int(row[4]), 'reference_description': row[5], 'sodium_addition_mg': row[17], 'original_nutrients': row[6:]}))

source = dict(id=12, name='IBGE / POF 2008–2009', kind='nutrients', cell='Tabela completa, publicação 2011', status='Importado do arquivo oficial', imported_count=len(foods), url='https://biblioteca.ibge.gov.br/visualizacao/livros/liv50002.pdf', download_url='https://ftp.ibge.gov.br/Orcamentos_Familiares/Pesquisa_de_Orcamentos_Familiares_2008_2009/Tabelas_de_Composicao_Nutricional_dos_Alimentos_Consumidos_no_Brasil/tabelacompleta.zip', sha256=hashlib.sha256((DIRECTORY / 'ibge-original.zip').read_bytes()).hexdigest())
(DIRECTORY / 'ibge-breakfast.json').write_text(json.dumps({'source': source, 'foods': foods}, ensure_ascii=False, indent=2), encoding='utf-8')
print(len(foods), dict(Counter(food['category'] for food in foods)))
print('\n'.join(food['name'] for food in foods))
