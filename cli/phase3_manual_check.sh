#!/bin/bash
set -e
rm -f /tmp/cj.txt /tmp/lu.html /tmp/lu2.html
curl -s -c /tmp/cj.txt -b /tmp/cj.txt 'http://127.0.0.1/local/kaznu/login_univer.php' -o /tmp/lu.html
SESS=$(grep -oP 'name="sesskey" value="\K[^"]+' /tmp/lu.html | head -1)
echo "SESSLEN=${#SESS}"
curl -s -c /tmp/cj.txt -b /tmp/cj.txt -L -X POST 'http://127.0.0.1/local/kaznu/login_univer.php' \
  --data-urlencode "sesskey=${SESS}" \
  --data-urlencode "_qf__local_kaznu_form_univer_login_form=1" \
  --data-urlencode "univer_login=not.a.real.user" \
  --data-urlencode "univer_password=WrongPassNeverLog" \
  --data-urlencode "wantsurl=/my/" \
  -o /tmp/lu2.html
echo '=== error markers ==='
grep -oE 'Неверный|Invalid|временно|temporarily|alert-danger|alert' /tmp/lu2.html | sort -u | head -20
echo '=== log leak check ==='
if sudo grep -R 'WrongPassNeverLog' /var/log/apache2/ /var/log/syslog 2>/dev/null | head -3; then
  echo 'LEAK_FOUND'
else
  echo 'NO_PASSWORD_IN_LOGS'
fi
if sudo grep -R 'WrongPassNeverLog\|/user/login?login=' /var/www/moodledata/ 2>/dev/null | head -3; then
  echo 'LEAK_IN_DATAROOT'
else
  echo 'NO_PASSWORD_IN_DATAROOT'
fi
sudo mysql -N moodle -e "SELECT id, eventname, LEFT(other,120) FROM mdl_logstore_standard_log WHERE eventname LIKE '%univer_login%' ORDER BY id DESC LIMIT 3" 2>/dev/null || echo 'no_events'
