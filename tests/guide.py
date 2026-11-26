import subprocess,re
from pathlib import Path
from bs4 import BeautifulSoup
from urllib.parse import urlparse,parse_qs
root=Path(__file__).resolve().parents[1]
for loc in ('en_US','ru_RU'):
 html=subprocess.check_output(['php',str(root/'tests/guide.php'),loc],text=True)
 s=BeautifulSoup(html,'html.parser'); ids=[x['id'] for x in s.select('[id]')]; assert len(ids)==len(set(ids))
 sections=s.select('section.w27-guide-section');assert len(sections)==14
 anchors=s.select('a[href^="#"]'); assert all(x['href'][1:] in ids for x in anchors)
 valid={'overview','ai','rag','index','ux','logs','diagnostics','vectors','quality','sources'}
 links=[a for a in s.select('a[href]') if '/wp-admin/' in a['href']]
 for a in links:
  q=parse_qs(urlparse(a['href']).query); assert q.get('page') in (['web27-site-search'],['web27-site-search-guide']); assert not q.get('tab') or q['tab'][0] in valid
 for section in re.findall(r"guideHint\('([^']+)'",(root/'StudioAdmin.php').read_text()):assert 'w27-guide-'+section in ids
 assert ('Руководство' in html) if loc=='ru_RU' else not re.search('[А-Яа-яЁё]',s.get_text())
 print(loc, len(sections),'sections;',len(anchors),'anchor links;',len(links),'admin navigation links; menu, plugin shortcut, access guard PASS')
