import json,subprocess,os,io
from PIL import Image
from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).parent
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=os.environ.get('CHROMIUM','/usr/bin/chromium'),args=['--no-sandbox'])
 page=b.new_page(viewport={'width':1280,'height':800});page.set_default_timeout(3500)
 errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 response={'mode':'external_ai','answer':'## Recommendations\n- [Alpha] [page:1] — details\n- **Beta** [page:2] — details\n- [Gamma]([page:3]) — details\n- **Useful** text\n<script>alert(1)</script> [Bad](javascript:alert(1)) https://outside.test/', 'sources':[{'url':'https://example.test/alpha/','title':'Alpha','excerpt':'Alpha public resource','image':'https://example.test/cover.png'},{'url':'https://example.test/beta/','title':'Beta','excerpt':'Some text','image':'https://outside.test/cover.png'},{'url':'https://example.test/gamma/','title':'Gamma','excerpt':'Some text','image':'https://example.test/cover.svg'},{'url':'https://example.test/alpha/','title':'Alpha duplicate','excerpt':'Duplicate'},{'url':'https://outside.test/','title':'Unsafe external'}], 'actions':[{'type':'link','url':'https://example.test/alpha/','label':'Duplicate link'},{'type':'query','query':'followup','label':'Follow up'}], 'topic':'test'}
 stub="<base href='https://example.test/'><script>window.received=[];window.fetch=async (url,opts)=>{received.push(JSON.parse(opts.body));return new Response(JSON.stringify("+json.dumps(response).replace('<','\\u003c')+"),{status:200});};</script>"
 buffer=io.BytesIO();Image.new('RGB',(64,64),(100,60,190)).save(buffer,format='PNG');png=buffer.getvalue()
 page.route('https://example.test/cover.png',lambda route:route.fulfill(status=200,content_type='image/png',body=png))
 page.route('https://outside.test/**',lambda route:route.abort())
 count=0
 for lang in ('en_US','ru_RU'):
  for window,cards,shape,placement in [('modal','grid','pill','bottom-right'),('right','list','rounded','top-left'),('left','carousel','circle','top-right'),('full','compact','rounded','bottom-left')]:
   for width in (320,375,768,1280):
    design={'window':window,'cards':cards,'shape':shape,'placement':placement,'mobile':'bottom-left','width':820,'height':800,'accent':'#ffcc00','visibility':'always'}
    html=subprocess.check_output(['php',str(root/'render.php'),lang,'custom','auto','',json.dumps(design)],text=True)
    page.set_viewport_size({'width':width,'height':800});page.set_content(html.replace('<head>','<head>'+stub,1))
    launcher=page.locator('[data-w27public-open]');assert launcher.is_visible();r=launcher.bounding_box();corner='bottom-left' if width<=640 else placement
    assert r['x']>=0 and r['x']+r['width']<=width+1
    assert abs(r['x']-16)<3 if corner.endswith('left') else abs(width-r['x']-r['width']-16)<3
    assert abs(r['y']-16)<3 if corner.startswith('top') else abs(800-r['y']-r['height']-16)<3
    launcher.click();dialog=page.locator('dialog');panel=dialog.locator('.w27public-panel');r=dialog.bounding_box();assert r['x']>=-1 and r['x']+r['width']<=width+1,(window,width,r)
    inp=panel.locator('[data-w27public-input]');inp.fill('question fixture');inp.press('Enter');answer=panel.locator('.w27public-answer-text');answer.wait_for();assert answer.locator('h3').count()==1 and answer.locator('ul li').count()==4
    assert answer.locator('a').all_text_contents()==['Alpha','Beta','Gamma'];assert '[Alpha]' not in answer.inner_text() and '**Beta**' not in answer.inner_text() and '[Gamma]' not in answer.inner_text()
    assert answer.locator('strong').inner_text()=='Useful' and answer.locator('script').count()==0
    src=panel.locator('.w27public-source');assert src.count()==3;assert src.locator('img').count()==1
    if cards!='compact':
     src.first.scroll_into_view_if_needed();page.wait_for_function('document.querySelector("dialog .w27public-source img").naturalWidth===64')
    assert panel.locator('.w27public-action-list a').count()==0
    assert panel.evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'),(window,width,'overflow')
    if cards=='carousel':assert panel.locator('.w27public-carousel-controls button').count()==2;panel.locator('.w27public-carousel-controls button').last.click()
    if cards=='compact':assert not panel.locator('.w27public-source-visual').first.is_visible()
    if shape=='circle':assert not launcher.locator('.w27public-launcher-label').is_visible()
    inp.press('Escape');assert not dialog.is_visible();count+=1
    print('PASS',lang,window,cards,width,flush=True)
 assert not errors,errors
 print('TOTAL',count,'new design/browser configurations PASS',flush=True)
 b.close()
