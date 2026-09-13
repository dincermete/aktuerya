#!/bin/bash
# Birden çok vaka filtresi için kesin.php çalıştırır, her anahtar değeri için ayrı (tırnak gerektirmez).
#   bash tests/canli/coklu.sh 07_,55_,118_ [anahtar deger1 deger2 ...]
cd "$(dirname "$0")/../.."
IFS=',' read -r -a filtreler <<< "$1"
anahtar="${2:-}"
degerler=("${@:3}")
[ ${#degerler[@]} -eq 0 ] && degerler=("")
for v in "${degerler[@]}"; do
  [ -n "$anahtar" ] && echo "--- $anahtar=$v"
  for f in "${filtreler[@]}"; do
    if [ -n "$anahtar" ]; then
      ddev exec php tests/canli/kesin.php "$f" "--set=$anahtar=$v" < /dev/null | tail -n +2
    else
      ddev exec php tests/canli/kesin.php "$f" < /dev/null | tail -n +2
    fi
  done
done
