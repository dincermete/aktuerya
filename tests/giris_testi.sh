#!/bin/bash
# Giriş korumasının uçtan uca denemesi: ddev exec bash tests/giris_testi.sh
set -u
cd "$(dirname "$0")/.."
for f in app/oturum.php config/giris.php public/giris.php public/cikis.php public/index.php public/api.php; do
  php -l "$f" | grep -v '^No syntax errors' || true
done
U=http://127.0.0.1
J=$(mktemp)
kod() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$@"; }
jeton() { curl -s -b "$J" -c "$J" "$U/giris.php" | sed -n 's/.*name="csrf" value="\([0-9a-f]*\)".*/\1/p' | head -1; }

echo "1) girişsiz ana sayfa:    $(kod -b "$J" -c "$J" "$U/index.php")"
echo "2) girişsiz API:          $(kod -b "$J" -c "$J" -X POST -H 'Content-Type: application/json' --data '{}' "$U/api.php")"
T=$(jeton)
echo "3) hatalı şifre:          $(curl -s -b "$J" -c "$J" --data-urlencode "csrf=$T" --data-urlencode 'kullanici=mdlife' --data-urlencode 'sifre=yanlis' "$U/giris.php" | grep -o 'Kullanıcı adı veya şifre hatalı' | head -1)"
T=$(jeton)
echo "4) jetonsuz giriş:        $(curl -s -b "$J" -c "$J" --data-urlencode 'kullanici=mdlife' --data-urlencode 'sifre=123456.!' "$U/giris.php" | grep -o 'Oturum süresi doldu' | head -1)"
T=$(jeton)
echo "5) doğru giriş:           $(kod -b "$J" -c "$J" --data-urlencode "csrf=$T" --data-urlencode 'kullanici=mdlife' --data-urlencode 'sifre=123456.!' "$U/giris.php")"
echo "6) girişli ana sayfa:     $(kod -b "$J" -c "$J" "$U/index.php")"
echo "7) girişli API:           $(curl -s -b "$J" -c "$J" -X POST -H 'Content-Type: application/json' --data @tests/ornek.json "$U/api.php" | head -c 60)"
C=$(curl -s -b "$J" -c "$J" "$U/index.php" | sed -n 's/.*name="csrf" value="\([0-9a-f]*\)".*/\1/p' | head -1)
echo "8) çıkış:                 $(kod -b "$J" -c "$J" --data-urlencode "csrf=$C" "$U/cikis.php")"
echo "9) çıkıştan sonra sayfa:  $(kod -b "$J" -c "$J" "$U/index.php")"
rm -f "$J"
