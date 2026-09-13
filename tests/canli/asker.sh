#!/bin/bash
# Askerlik vakalarının girdileri, motor askerlik aralığı ve dönem gelirleri
cd "$(dirname "$0")/../.."
python3 - <<'PY'
import json
c = json.load(open('tests/canli/cases/cases.json', encoding='utf-8'))
for k, v in c.items():
    if 'asker' in k or k in ('01_base', '15_askerlik'):
        print(k, {x: v[x] for x in v if x in ('DogTarF', 'OlayTarF', 'RapTarF', 'askeryasF', 'askersureF', 'CheckboxAskerDegil', 'RadioGroup40', 'KendiCinsF')})
PY
grep -n "asker" tests/canli/karsilastir.php
for v in 15_askerlik 34_askerlik_dus 35_askerlik_22_12 95_askerlik_21_12 96_askerlik_21_3 97_askerlik_23_6; do
  ddev exec php tests/canli/dokum.php "$v" 2>&1 | grep -E "^==|^olay|kazalı yaş|kazalı geliri|Askerlik"
done
