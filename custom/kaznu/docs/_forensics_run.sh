#!/bin/bash
set -u
echo "================================================================"
echo "FARABI DOWNTIME FORENSICS $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "================================================================"

echo ""
echo "### A. SYSTEM SNAPSHOT"
uptime
nproc
free -h
swapon --show
df -h / | tail -1

echo ""
echo "### B. OOM / kernel kills (dmesg)"
dmesg -T 2>/dev/null | grep -iE 'Out of memory|oom-kill|Killed process|invoked oom' | tail -40

echo ""
echo "### C. systemd journal OOM / MariaDB / Apache (last 48h)"
journalctl --since "48 hours ago" --no-pager 2>/dev/null | grep -iE 'Out of memory|Killed process|oom-kill|mariadb|mysql|apache2.*(fail|error|segfault)|oom' | tail -60

echo ""
echo "### D. MariaDB status / error log recent"
systemctl status mariadb --no-pager -l 2>&1 | head -30
for f in /var/log/mysql/error.log /var/log/mysql/mariadb.err /var/log/mariadb/mariadb.log /var/lib/mysql/*.err; do
  if [ -f "$f" ]; then
    echo "--- $f last 80 ---"
    tail -80 "$f"
  fi
done

echo ""
echo "### E. Apache error log (48h filter if timestamps)"
if [ -f /var/log/apache2/error.log ]; then
  echo "--- error.log last 100 ---"
  tail -100 /var/log/apache2/error.log
fi
if [ -f /var/log/apache2/farabischool_error.log ]; then
  echo "--- farabischool_error.log last 80 ---"
  tail -80 /var/log/apache2/farabischool_error.log
fi

echo ""
echo "### F. MPM config now vs backups"
ls -la /etc/apache2/mods-enabled/mpm_prefork.conf* 2>/dev/null
echo "--- current ---"
cat /etc/apache2/mods-enabled/mpm_prefork.conf

echo ""
echo "### G. ACCESS LOG VOLUME BY HOUR (today)"
ALOG=/var/log/apache2/farabischool_access.log
if [ ! -f "$ALOG" ]; then ALOG=$(ls -1t /var/log/apache2/*access* 2>/dev/null | head -1); fi
echo "using ALOG=$ALOG"
if [ -f "$ALOG" ]; then
  # Apache combined: [10/Aug/2026:08:00:01 +0000]
  echo "--- requests per hour today UTC ---"
  grep '10/Aug/2026' "$ALOG" 2>/dev/null | awk -F'[/: ]' '{print $5":"$6}' | sort | uniq -c | sort -k2
  echo "--- requests per minute 07:30-08:30 ---"
  grep -E '10/Aug/2026:0[78]:' "$ALOG" 2>/dev/null | awk -F: '{print $2":"$3}' | sort | uniq -c | sort -k2 | tail -80
fi

echo ""
echo "### H. TOP IPs today + around incident window"
if [ -f "$ALOG" ]; then
  echo "--- top 20 IPs full today sample (last 20k lines) ---"
  tail -n 20000 "$ALOG" | awk '{print $1}' | sort | uniq -c | sort -rn | head -20
  echo "--- top IPs during 07:00-09:00 ---"
  grep -E '10/Aug/2026:0[789]:' "$ALOG" 2>/dev/null | awk '{print $1}' | sort | uniq -c | sort -rn | head -20
  echo "--- top paths during 07:00-09:00 ---"
  grep -E '10/Aug/2026:0[789]:' "$ALOG" 2>/dev/null | awk '{print $7}' | sort | uniq -c | sort -rn | head -25
  echo "--- status codes during 07:00-09:00 ---"
  grep -E '10/Aug/2026:0[789]:' "$ALOG" 2>/dev/null | awk '{print $9}' | sort | uniq -c | sort -rn | head -15
  echo "--- User-Agents top during window ---"
  grep -E '10/Aug/2026:0[789]:' "$ALOG" 2>/dev/null | awk -F\" '{print $6}' | sort | uniq -c | sort -rn | head -15
fi

echo ""
echo "### I. rotated logs Aug 9 if any"
ls -lah /var/log/apache2/ | head -40

echo ""
echo "### J. sysstat / sar if present"
sar -q 2>/dev/null | tail -20 || true
sar -r 2>/dev/null | tail -15 || true
which vmstat >/dev/null && vmstat 1 3

echo ""
echo "### K. Moodle cron frequency"
grep -R cron /etc/cron* 2>/dev/null | grep -i moodle || true
ls -la /etc/cron.d/ 2>/dev/null
cat /etc/cron.d/*moodle* 2>/dev/null || true
crontab -u www-data -l 2>/dev/null || true
ls -la /var/www/moodle/admin/cli/cron.php

echo ""
echo "### L. PHP / mod_php OOM related"
grep -iE 'memory|Fatal|Allowed memory|Cannot kill' /var/log/apache2/error.log 2>/dev/null | tail -30
grep -iE 'memory|Fatal' /var/log/apache2/farabischool_error.log 2>/dev/null | tail -20

echo ""
echo "### M. Disk IO / apache children peak notes from status"
systemctl show apache2 -p NRestarts -p ActiveEnterTimestamp -p MemoryCurrent -p MemoryPeak --no-pager 2>/dev/null
systemctl show mariadb -p NRestarts -p ActiveEnterTimestamp -p MemoryCurrent -p MemoryPeak --no-pager 2>/dev/null

echo ""
echo "### N. auth/bot scan markers around incident"
if [ -f "$ALOG" ]; then
  echo "scanner paths count 07-09:"
  grep -E '10/Aug/2026:0[789]:' "$ALOG" 2>/dev/null | grep -iE 'wp-admin|xmlrpc|\.php~|\.env|nacos|solr|shiro|actuator|phpmyadmin|cgi-bin|\.git' | wc -l
  echo "sample scanner hits:"
  grep -E '10/Aug/2026:0[789]:' "$ALOG" 2>/dev/null | grep -iE 'wp-admin|xmlrpc|\.env|nacos|solr|actuator|phpmyadmin' | head -20
fi

echo ""
echo "### O. MariaDB uptime / processlist"
mysql -N -e "SHOW GLOBAL STATUS LIKE 'Uptime'; SHOW GLOBAL STATUS LIKE 'Threads_connected'; SHOW GLOBAL STATUS LIKE 'Max_used_connections'; SHOW GLOBAL STATUS LIKE 'Aborted_connects'; SHOW VARIABLES LIKE 'max_connections'; SHOW VARIABLES LIKE 'innodb_buffer_pool_size';" 2>/dev/null || true

echo ""
echo "### P. public health now"
curl -sS -o /dev/null -w "root=%{http_code} t=%{time_total}\n" --max-time 15 https://farabischool.kz/ || true
curl -sS -o /dev/null -w "landing=%{http_code} t=%{time_total}\n" --max-time 20 https://farabischool.kz/local/kaznu/landing.php || true

echo "FORENSICS_DONE"
