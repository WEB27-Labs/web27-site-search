import gettext,json,re,subprocess
from pathlib import Path
from babel.messages.pofile import read_po
root=Path(__file__).resolve().parents[1]
php=r'''$all=[];foreach(glob('*.php') as $f){$t=array_values(array_filter(token_get_all(file_get_contents($f)),fn($x)=>!is_array($x)||!in_array($x[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT])));foreach($t as $i=>$v){if(is_array($v)&&$v[0]===T_STRING&&in_array($v[1],['__','esc_html__','esc_attr__','_e','esc_html_e','esc_attr_e','_x','_n'])&&($t[$i+1]??null)==='('){$s=$t[$i+2]??null;if(!is_array($s)||$s[0]!==T_CONSTANT_ENCAPSED_STRING){fwrite(STDERR,"Nonliteral $f:$v[2]\n");continue;} $all[]=eval('return '.$s[1].';');}}}echo json_encode(array_values(array_unique($all)));'''
actual=set(json.loads(subprocess.check_output(['php','-r',php],cwd=root,text=True)))
with (root/'languages/web27-site-search.pot').open('rb') as f: pot={m.id for m in read_po(f) if m.id}
with (root/'languages/web27-site-search-ru_RU.po').open('rb') as f: po={m.id:m.string for m in read_po(f) if m.id and not m.fuzzy}
with (root/'languages/web27-site-search-ru_RU.mo').open('rb') as f: mo={k:v for k,v in gettext.GNUTranslations(f)._catalog.items() if k}
fixture=json.loads((root/'tests/messages-ru.json').read_text())
print('source',len(actual),'POT',len(pot),'PO',len(po),'MO',len(mo),'fixture',len(fixture))
print('missing source strings:',sorted(actual-pot));print('unused catalog strings:',sorted(pot-actual))
assert actual==pot==set(po)==set(mo)==set(fixture)
assert po==mo==fixture and all(po.values())
pat=r'%(?:\d+\$)?[sdif]'
for k,v in po.items(): assert sorted(re.findall(pat,k))==sorted(re.findall(pat,v)),k
print('PASS exact catalog parity, no missing/fuzzy entries, format placeholders')
