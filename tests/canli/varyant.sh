#!/bin/bash
# Kullanım: bash tests/canli/varyant.sh "<kesin filtre>" "<karsilastir filtre>" anahtar deger1 deger2 ...
cd "$(dirname "$0")/../.."
kesinF="$1"; karsF="$2"; anahtar="$3"; shift 3
for v in "$@"; do
  echo "--- $anahtar=$v"
  [ -n "$kesinF" ] && ddev exec php tests/canli/kesin.php "$kesinF" "--set=$anahtar=$v"
  [ -n "$karsF" ] && ddev exec php tests/canli/karsilastir.php "$karsF" "--set=$anahtar=$v" | grep -E 'TUTMADI|^== '
done
