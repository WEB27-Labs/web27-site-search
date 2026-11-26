import json,subprocess,os
from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).parent
sources=[{'title':'Relevant A','url':'https://example.test/a','excerpt':'A'},{'title':'Unrelated B','url':'https://example.test/b','excerpt':'B'},{'title':'Relevant C','url':'https://example.test/c','excerpt':'C'}]
actions=[{'type':'link','url':'https://example.test/b','label':'Unrelated B'},{'type':'link','url':'https://example.test/d','label':'Navigation'}]
scenarios=[('external_ai','Try [page:3] and [page:1].',['Relevant C','Relevant A'],0),('external_ai','No confirmed match.',[],0),('external_ai','Unknown [page:9] and https://outside.test/',[],0),('external_ai','Read [Relevant A](https://example.test/a).',['Relevant A'],0),('local','Local result.',['Relevant A','Unrelated B','Relevant C'],1),('local_fallback','Fallback.',['Relevant A','Unrelated B','Relevant C'],1),('welcome','Welcome.',['Relevant A','Unrelated B','Relevant C'],1)]
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=os.environ.get('CHROMIUM','/usr/bin/chromium'),args=['--no-sandbox']);page=b.new_page(viewport={'width':1280,'height':800});page.set_default_timeout(3000)
 count=0
 for lang in ('en_US','ru_RU'):
  for mode,answer,expected,links in scenarios:
   data={'mode':mode,'answer':answer,'sources':sources,'actions':actions,'topic':'fixture'}
   stub='<base href="https://example.test/"><script>window.fetch=async()=>new Response(JSON.stringify('+json.dumps(data)+'),{status:200})</script>'
   html=subprocess.check_output(['php',str(root/'render.php'),lang,'dark'],text=True)
   page.set_content(html.replace('<head>','<head>'+stub,1));page.locator('[data-w27public-open]').click();panel=page.locator('dialog .w27public-panel');inp=panel.locator('[data-w27public-input]');inp.fill('fixture question');inp.press('Enter');panel.locator('.w27public-answer-text').wait_for()
   assert panel.locator('.w27public-source-title').all_text_contents()==expected,(lang,mode,answer,panel.locator('.w27public-source-title').all_text_contents())
   assert panel.locator('.w27public-action-list a').count()==links
   assert panel.locator('[data-w27public-sources]').is_visible()==bool(expected)
   count+=1;print('PASS',lang,mode,answer,flush=True)
 b.close();print('TOTAL',count,'PASS')
