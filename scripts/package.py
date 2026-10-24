"""Build a reproducible WordPress ZIP with one kourentzes-tickets/ root."""
from pathlib import Path
import hashlib
import re
import zipfile
root=Path(__file__).resolve().parent.parent
version=re.search(r'\* Version:\s*([^\s]+)',(root/'kourentzes-tickets.php').read_text()).group(1)
out=root/'dist';out.mkdir(exist_ok=True)
target=out/f'kourentzes-tickets-{version}.zip'
files=[root/'kourentzes-tickets.php',root/'uninstall.php',root/'LICENSE',root/'README.md',root/'readme.txt',root/'CHANGELOG.md']
for folder in ['includes','assets','languages']:
    files += [p for p in (root/folder).rglob('*') if p.is_file()]
for name in ['RUNBOOK.md','ARCHITECTURE.md','PRIVACY-NOTICE.md','TEST-REPORT.md']:
    files.append(root/'docs'/name)
with zipfile.ZipFile(target,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for path in sorted(files):
        entry=zipfile.ZipInfo('kourentzes-tickets/'+path.relative_to(root).as_posix(),date_time=(2026,10,24,0,0,0))
        entry.compress_type=zipfile.ZIP_DEFLATED;entry.external_attr=0o100644<<16
        z.writestr(entry,path.read_bytes())
with zipfile.ZipFile(target) as z:
    assert z.testzip() is None
    assert all(name.startswith('kourentzes-tickets/') and '..' not in name.split('/') for name in z.namelist())
    assert 'kourentzes-tickets/kourentzes-tickets.php' in z.namelist()
digest=hashlib.sha256(target.read_bytes()).hexdigest()
(target.with_suffix('.zip.sha256')).write_text(f'{digest}  {target.name}\n')
print(f'{target.name}: {len(files)} files, {target.stat().st_size} bytes; SHA-256 {digest}')
