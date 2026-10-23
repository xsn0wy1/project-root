"""Translation contracts, safe HTML bindings, and optional pre-change Spanish preservation."""
from pathlib import Path
from html.parser import HTMLParser
from collections import Counter
import argparse,json,re
from urllib.parse import urlsplit,parse_qs
ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--baseline',type=Path);parser.add_argument('--rendered-pages',type=Path);parser.add_argument('--allow-added-ids',action='store_true',help='Preserve every baseline ID while permitting explicitly added UI controls.');parser.add_argument('--removed-keys',type=Path,help='Explicitly reviewed removed-key inventory; reject reintroduced keys or references.');parser.add_argument('--asset-version',help='Require this shared version on every local script/stylesheet URL.');args=parser.parse_args()
def load(path):
 def unique(pairs):
  result={}
  for key,value in pairs:
   assert key not in result,'Duplicate translation key: '+key
   result[key]=value
  return result
 return json.loads(path.read_text(),object_pairs_hook=unique)
es=load(ROOT/'db/json/es.json');en=load(ROOT/'db/json/en.json')
assert es.keys()==en.keys(),'Dictionary keys differ'
def strings(value):
 if isinstance(value,str):return [value]
 assert isinstance(value,dict) and set(value)=={'one','other'},value
 return list(value.values())
for key in es:
 assert re.fullmatch('[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+',key),key
 assert type(es[key]) is type(en[key]),key
 for a,b in zip(strings(es[key]),strings(en[key])):
  assert a and b,key
  assert set(re.findall(r'\{[a-zA-Z][a-zA-Z0-9_]*\}',a))==set(re.findall(r'\{[a-zA-Z][a-zA-Z0-9_]*\}',b)),key
class Page(HTMLParser):
 def __init__(self):super().__init__(convert_charrefs=True);self.stack=[];self.text=[];self.attrs=[];self.scripts=[];self.styles=[];self.ids=[]
 def handle_starttag(self,tag,attrs):
  attrs=dict(attrs)
  if self.stack:assert not self.stack[-1][1].get('data-i18n'), 'Text binding would remove a nested control: '+str(self.stack[-1])
  if 'id' in attrs:self.ids.append(attrs['id'])
  if tag=='script' and attrs.get('src'):self.scripts.append(attrs['src'])
  if tag=='link' and attrs.get('rel')=='stylesheet' and attrs.get('href'):self.styles.append(attrs['href'])
  if tag=='option' and attrs.get('data-i18n'):assert 'value' in attrs,'Translated options require stable values'
  for name,value in attrs.items():
   if name.startswith('data-i18n') and not name.endswith('-params'):assert value in es,(name,value)
   if name in ['title','placeholder','aria-label','aria-description','alt']:self.attrs.append((name,value))
  if tag not in {'meta','link','img','input','br','hr','source','wbr','area','base','embed','param','track','col','path','circle','rect','line','use','ellipse'}:self.stack.append((tag,attrs))
 def handle_endtag(self,tag):
  while self.stack:
   if self.stack.pop()[0]==tag:break
 def handle_startendtag(self,tag,attrs):self.handle_starttag(tag,attrs);self.handle_endtag(tag)
 def handle_data(self,data):
  if data.strip() and not any(tag in ['script','style'] for tag,attrs in self.stack):self.text.append(data.strip())
files=[ROOT/'public/index.php',*(ROOT/'public/src/views').rglob('*.html'),*(ROOT/'public/src/views').rglob('*.php')]
baseline=json.loads(args.baseline.read_text()) if args.baseline else None
rendered=json.loads(args.rendered_pages.read_text()) if args.rendered_pages else {}
versioned_assets=0
for path in files:
 relative=str(path.relative_to(ROOT))
 if path.suffix=='.php':
  assert relative in rendered,'Provide --rendered-pages from an authenticated baseline capture: '+relative
 page=Page();page.feed(rendered[relative] if path.suffix=='.php' else path.read_text())
 if path.name=='error.php':
  assert len(page.scripts)==1 and 'theme.js' in page.scripts[0],path
 else:
  assert 'i18n.js' in next(src for src in page.scripts if 'theme.js' not in src),path
 for src in page.scripts+page.styles:
  if not src.startswith(('http:','https:')):
   asset=ROOT/'public'/src.lstrip('/') if src.startswith('/') else path.parent/src
   assert Path(str(asset).split('?')[0]).resolve().is_file(),(path,src)
   if args.asset_version:
    assert parse_qs(urlsplit(src).query).get('v')==[args.asset_version],('Asset version differs',path,src)
    versioned_assets+=1
 if baseline and path.name!='error.php':
  before=Page();before.feed(baseline[relative if relative in baseline else relative.replace('.php','.html')])
  assert not (Counter(before.text)-Counter(page.text)),('Spanish fallback changed',path,Counter(before.text)-Counter(page.text))
  assert not (Counter(before.attrs)-Counter(page.attrs)),('Spanish attribute changed',path)
  assert not (Counter(before.ids)-Counter(page.ids)) if args.allow_added_ids else Counter(before.ids)==Counter(page.ids),('Static IDs changed',path)
used=set()
for path in [*(ROOT/'public/src').rglob('*.js'),*(ROOT/'backend').rglob('*.php')]:
 if path.name=='local.php':continue
 used.update(re.findall(r'(?:HospitalI18n\.(?:t|mark|html)|I18n::t)\(\s*[\'\"]([^\'\"]+)',path.read_text()))
assert not (used-es.keys()),sorted(used-es.keys())
for code in ['admin','staff','records_manager','dispatcher']:
 assert 'account_role_code.'+code in es,'Dynamic account-role translation missing: '+code
assert any(key.startswith('catalog.document_categories.') for key in es),'Dynamic category translations missing'
if args.removed_keys:
 removed=set(load(args.removed_keys)['removed_keys'])
 assert not (removed & es.keys()),'Removed keys reintroduced: '+str(sorted(removed & es.keys()))
 for path in [*(ROOT/'public').rglob('*.php'),*(ROOT/'public').rglob('*.js'),*(ROOT/'backend').rglob('*.php')]:
  if 'vendor' in path.parts or path.name=='local.php':continue
  source=path.read_text()
  assert not any(key in source for key in removed),'Removed key still referenced: '+str(path)
print(f'PASS {len(es)} bilingual keys, interpolation/plural contracts, {len(files)} page bindings/assets, {len(used)} literal key references'+(' and original Spanish fallback/attribute/ID preservation' if baseline else ''))

if args.asset_version:print(f'PASS {versioned_assets} local script/stylesheet URLs use the shared asset version')
