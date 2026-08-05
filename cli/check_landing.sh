#!/bin/bash
cd /tmp || exit 1
rm -f cj.txt out.html login.html
curl -s -c cj.txt -b cj.txt 'http://127.0.0.1/login/index.php' -o login.html
TOKEN=$(grep -oP 'name="logintoken" value="\K[^"]+' login.html | head -1)
echo "TOKEN=$TOKEN"
curl -s -c cj.txt -b cj.txt -L -X POST 'http://127.0.0.1/login/index.php' \
  -d "username=demo_student&password=Student2026!&logintoken=${TOKEN}" -o afterlogin.html
echo "LOGIN=$(grep -c 'demo_student\|Вы вошли\|logout' afterlogin.html)"
curl -s -c cj.txt -b cj.txt -L 'http://127.0.0.1/local/kaznu/landing.php' -o out.html
echo "BYTES=$(wc -c < out.html)"
echo "TILES=$(grep -c kzn-tile out.html)"
echo "FACTS=$(grep -c kzn-facts out.html)"
echo "FOOTER=$(grep -c kzn-footer out.html)"
echo "HUD=$(grep -c local-kaznu-hud out.html)"
echo "PAGE=$(grep -c 'id=\"page\"' out.html)"
grep -nE 'Fatal|Exception|Parse error' out.html | head -20
grep -n 'kzn-facts\|kzn-about\|id="courses"\|kzn-footer\|local-kaznu-hud\|kzn-tile' out.html | head -40
