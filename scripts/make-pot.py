"""Extract literal translation calls and dynamic interface labels into a portable English catalog."""
import json
import re
from pathlib import Path
root=Path(__file__).resolve().parent.parent
strings=set()
for path in [root/'kourentzes-tickets.php',*(root/'includes').glob('*.php')]:
    text=path.read_text()
    for m in re.finditer(r"(?:__|esc_html__|esc_html_e)\(\s*'((?:[^'\\]|\\.)*)'",text):
        strings.add(m.group(1).replace("\\'","'").replace('\\\\','\\'))
    # Select/input labels are stored as PHP literal map values and translated at render time.
    for m in re.finditer(r"=>\s*'([^'\n]+)'",text):
        value=m.group(1)
        if re.search(r'[A-Z]| ',value) and not value.startswith(('SELECT ','DELETE ','UPDATE ','INSERT ','[ktickets_','Content-','Cache-')) and '<' not in value:
            strings.add(value)
strings.update(['open','in_progress','waiting_requester','resolved','closed','access','rectification','erasure','restriction','objection','portability','other','technical','suggestion','privacy'])
header='Project-Id-Version: Kourentzes Tickets 0.1.0\nReport-Msgid-Bugs-To: https://github.com/emperorkk/kourentzes-tickets/issues\nPOT-Creation-Date: 2026-10-24 00:00+0000\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n'
lines=['# Kourentzes Tickets English translation template.','msgid ""','msgstr '+json.dumps(header,ensure_ascii=False),'']
for value in sorted(strings):
    lines += ['msgid '+json.dumps(value,ensure_ascii=False),'msgstr ""','']
(root/'languages/kourentzes-tickets.pot').write_text('\n'.join(lines))
print(f'Catalog: {len(strings)} strings')
