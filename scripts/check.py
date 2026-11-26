#!/usr/bin/env python3
import argparse
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import zipfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description='Run isolated source and optional browser tests (no live APIs).')
parser.add_argument('--browser', action='store_true')
args = parser.parse_args()

def run(*command, **kwargs):
    print('RUN', *map(str, command), flush=True)
    subprocess.run(list(map(str, command)), cwd=root, check=True, **kwargs)

for path in sorted(root.glob('*.php')) + sorted((root / 'tests').glob('*.php')):
    run('php', '-l', path.relative_to(root))
for path in sorted((root / 'assets').glob('*.js')):
    run('node', '--check', path.relative_to(root))
for name in ('smoke', 'providers', 'admin-providers', 'design', 'language', 'embedding-cron', 'final-security', 'universal', 'submission'):
    run('php', f'tests/{name}.php')
for mode in ('different', 'same', 'explicit'):
    run('php', 'tests/server-constants.php', mode)
for name in ('catalogs', 'guide'):
    run(sys.executable, f'tests/{name}.py')
with tempfile.TemporaryDirectory() as temp:
    for name in ('retrieval', 'independent-retrieval'):
        with (Path(temp) / f'{name}.jsonl').open('w') as out:
            run('php', f'tests/{name}.php', stdout=out)
        import json
        rows = [json.loads(line) for line in (Path(temp) / f'{name}.jsonl').read_text().splitlines()]
        assert rows, 'Empty retrieval benchmark'
        print(name, 'top-one', sum(r['expected'] == r['top'] for r in rows), '/', len(rows), 'benchmark, not a pass threshold')
    archive = Path(temp) / 'web27-site-search.zip'
    run(sys.executable, 'scripts/build.py', archive)
    with zipfile.ZipFile(archive) as z:
        assert z.testzip() is None
        assert all(name.startswith('web27-site-search/') for name in z.namelist())
        assert 'web27-site-search/web27-site-search.php' in z.namelist()
if args.browser:
    env = dict(os.environ)
    if not env.get('CHROMIUM'):
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            env['CHROMIUM'] = shutil.which('chromium') or p.chromium.executable_path
    for name in ('browser', 'design-browser', 'design-preview', 'relevance-browser'):
        run(sys.executable, f'tests/{name}.py', env=env)
print('Completed isolated checks. No live WordPress or provider certification.', flush=True)
