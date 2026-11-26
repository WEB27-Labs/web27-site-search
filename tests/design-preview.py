import subprocess,os
from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).parent
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.environ.get('CHROMIUM','/usr/bin/chromium'),args=['--no-sandbox']);page=browser.new_page(viewport={'width':1280,'height':900});errs=[];page.on('pageerror',lambda e:errs.append(str(e)))
 for loc in ('en_US','ru_RU'):
  page.set_content(subprocess.check_output(['php',str(root/'design-preview.php'),loc],text=True))
  preview=page.locator('[data-w27public-preview]');launcher=page.locator('[data-w27public-preview-launcher]')
  page.locator('[name=appearance]').select_option('custom');page.locator('[name="design[placement]"]').select_option('top-left');page.locator('[name="design[shape]"]').select_option('circle');page.locator('[name="design[cards]"]').select_option('carousel');page.locator('[name="design[width]"]').fill('640');page.locator('[name="design[accent]"]').fill('#ffcc00')
  assert preview.get_attribute('data-w27public-theme')=='custom' and preview.get_attribute('data-w27-cards')=='carousel' and launcher.get_attribute('data-w27-shape')=='circle';assert launcher.get_attribute('data-w27-placement')=='top-left';assert preview.evaluate('(e)=>e.style.getPropertyValue("--w27-width")')=='640px';assert preview.evaluate('(e)=>e.style.getPropertyValue("--w27-custom-button-text")')=='#000'
  page.locator('[name="design[mobile]"]').select_option('bottom-right');page.locator('[data-w27-preview-mobile]').check();assert launcher.get_attribute('data-w27-placement')=='bottom-right';assert preview.get_attribute('data-mobile')=='1'
  page.locator('[name="design[visibility]"]').select_option('never');assert not launcher.is_visible();page.locator('[name="design[visibility]"]').select_option('always');assert launcher.is_visible()
  page.locator('[name="settings[panel_title]"]').fill('New title');assert preview.locator('h2').inner_text()=='New title'
  print('PASS live design preview',loc,flush=True)
 assert not errs,errs
 browser.close()
