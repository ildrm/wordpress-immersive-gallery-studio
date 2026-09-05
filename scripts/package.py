#!/usr/bin/env python3
"""Build a reproducible installable ZIP containing only plugin runtime files."""
from pathlib import Path
import re
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
version = re.search(r"define\( 'IGS_VERSION', '([^']+)'", (root / 'immersive-gallery-studio.php').read_text()).group(1)
files = [root / name for name in ('immersive-gallery-studio.php', 'uninstall.php', 'readme.txt', 'README.md', 'LICENSE')]
for directory in ('src', 'assets', 'templates'):
    files.extend(path for path in (root / directory).rglob('*') if path.is_file() and not path.name.startswith('.'))
output = root / 'dist' / f'immersive-gallery-studio-{version}.zip'
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', compression=ZIP_DEFLATED) as archive:
    for path in sorted(files):
        info = ZipInfo('immersive-gallery-studio/' + path.relative_to(root).as_posix(), (2026, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, path.read_bytes())
with ZipFile(output) as archive:
    assert archive.testzip() is None
    assert 'immersive-gallery-studio/immersive-gallery-studio.php' in archive.namelist()
    assert len([name for name in archive.namelist() if name.endswith('/manifest.php')]) == 10
print(f'{output} ({len(files)} files)')
