#!/bin/bash
# Tutmayan aralıkları vaka adıyla ve genel özeti gösterir; kesin ölçümlerde %0,04'ü aşan farkları listeler.
#   bash tests/canli/ozet.sh [filtre] [--set=anahtar=deger ...]
cd "$(dirname "$0")/../.."
ddev exec php tests/canli/karsilastir.php "$@" > /tmp/kars.txt 2>&1
awk '/^== /{vaka=$0} /TUTMADI/{if(vaka!=""){print vaka; vaka=""} print} /GENEL/{print}' /tmp/kars.txt
echo "--- kesin (|fark| > %0,04)"
ddev exec php tests/canli/kesin.php "$@" > /tmp/kesin.txt 2>&1
awk 'NR>1 { f=$NF; gsub("%","",f); gsub(",",".",f); if (f!="-" && (f+0 > 0.04 || f+0 < -0.04)) print }' /tmp/kesin.txt
