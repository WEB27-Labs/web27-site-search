#!/usr/bin/env python3
import sys, zipfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
files=list(root.glob('*.php'))+[root/x for x in ('readme.txt','LICENSE')]
files += [p for d in ('assets','languages') for p in (root/d).rglob('*') if p.is_file()]
with zipfile.ZipFile(sys.argv[1], 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for path in sorted(files):
        zi=zipfile.ZipInfo('web27-site-search/'+path.relative_to(root).as_posix(),(2026,11,25,0,0,0))
        zi.compress_type=zipfile.ZIP_DEFLATED;zi.external_attr=0o100644<<16
        z.writestr(zi,path.read_bytes(),compresslevel=9)
