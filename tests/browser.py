import subprocess, os, json
from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).parent
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=os.environ.get('CHROMIUM','/usr/bin/chromium'),args=['--no-sandbox'])
 print('Chromium',b.version,flush=True)
 page=b.new_page(viewport={'width':320,'height':800}); page.set_default_timeout(2000)
 errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
 for lang in ('en_US','ru_RU'):
  for scheme in ('light','dark','contrast','system'):
   html=subprocess.check_output(['php',str(root/'render.php'),lang,scheme],text=True)
   for width in (320,375,768,1280,1440):
    response={'mode':'local_fallback','answer':'[page:1] <script>alert(1)</script> [Bad](javascript:alert(1)) https://other.test/', 'sources':[{'url':'https://example.test/resource/1/','title':'Public fixture','excerpt':'Long text '*20},{'url':'https://other.test/','title':'Unsafe','excerpt':'bad'}], 'vote_token':'fixture','topic':'fixture'}
    stub="<base href='https://example.test/'><script>window.received=[];window.fetch=async (url,opts)=>{if(url.includes('/query')){received.push(JSON.parse(opts.body));return new Response(JSON.stringify("+json.dumps(response).replace('<','\\u003c')+"),{status:200});}if(url.includes('/vote'))return new Response('{\"ok\":true}',{status:200});throw Error('Unexpected network');};</script>"
    page.set_viewport_size({'width':width,'height':800});page.set_content(html.replace('<head>','<head>'+stub,1))
    for root_selector in ('.is-inline','.is-modal'):
     if root_selector=='.is-modal':page.locator('[data-w27public-open]').click()
     panel=page.locator(root_selector);assert panel.is_visible()
     assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),(lang,scheme,width,root_selector,'document overflow')
     assert panel.evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'),(lang,scheme,width,root_selector,'panel overflow')
     inp=panel.locator('[data-w27public-input]');inp.fill('question fixture');inp.press('Enter')
     answer=panel.locator('[data-w27public-answer]');answer.wait_for(state='visible')
     assert page.evaluate('received.at(-1).locale')==lang
     assert answer.locator('a').count()==1 and answer.locator('a').get_attribute('href')=='https://example.test/resource/1/'
     assert answer.locator('script').count()==0 and '<script>' in answer.inner_text()
     assert panel.locator('[data-w27public-state]').inner_text()==page.evaluate('Web27SiteSearch.strings.fallback')
     panel.locator('[data-w27public-vote]').first.click();page.wait_for_function('(s)=>document.querySelector(s+" [data-w27public-vote]").disabled',arg=root_selector)
     assert panel.evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'),(lang,scheme,width,root_selector,'answer overflow')
     panel.locator('[data-w27public-reset]').click();assert answer.is_hidden()
     if root_selector=='.is-modal':
      inp.press('Escape');page.wait_for_function('!document.querySelector("dialog").open && document.activeElement.hasAttribute("data-w27public-open")')
    print('PASS',lang,scheme,width,'inline + modal',flush=True)
 for site,forced,expected in [('en_US','ru_RU','ru_RU'),('ru_RU','en_US','en_US'),('en_US','auto','en_US'),('ru_RU','auto','ru_RU')]:
  html=subprocess.check_output(['php',str(root/'render.php'),site,'system',forced,'no-pack'],text=True)
  page.set_content(html)
  assert page.evaluate('Web27SiteSearch.locale')==expected
  assert page.locator('.is-inline').get_attribute('lang')==expected.replace('_','-')
  assert page.locator('[data-w27public-open]').get_attribute('lang')==expected.replace('_','-')
  label=page.locator('.is-inline [data-w27public-form] button').inner_text()
  assert label==page.evaluate('Web27SiteSearch.strings.submit')
  assert (label=='Find an answer')==(expected=='en_US')
  assert page.locator('[data-w27public-open]').inner_text()==('Site Search' if expected=='en_US' else 'Умный поиск')
  print('PASS widget locale override without installed extra WP pack',site,forced,expected,flush=True)
 assert not errors,errors
 print('TOTAL 40 configurations / 80 panel runs PASS',flush=True)
 b.close()
