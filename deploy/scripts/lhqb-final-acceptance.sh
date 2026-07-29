#!/usr/bin/env bash
set -Eeuo pipefail

if [ -f /etc/lhqb/deploy.env ]; then
  # shellcheck disable=SC1091
  source /etc/lhqb/deploy.env
fi

DOMAIN="${LHQB_DOMAIN:-neuranet.site}"
CURRENT="$(readlink -f /data/lhqb/current)"

nginx -t >/dev/null
for service in nginx php8.1-fpm mysql redis-server; do
  systemctl is-active --quiet "${service}"
done
systemctl is-enabled --quiet lhqb-smtp-mss.service
systemctl is-active --quiet lhqb-smtp-mss.service
for timer in lhqb-news-crawler.timer lhqb-backup.timer lhqb-health-check.timer; do
  systemctl is-enabled --quiet "${timer}"
  systemctl is-active --quiet "${timer}"
done

test -L /data/lhqb/current
test "$(readlink -f "${CURRENT}/data/conf")" = '/data/lhqb/shared/data/conf'
test "$(readlink -f "${CURRENT}/data/runtime")" = '/data/lhqb/shared/data/runtime'
test "$(readlink -f "${CURRENT}/public/upload")" = '/data/lhqb/shared/public/upload'

curl --silent --show-error --fail --max-time 20 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/" >/dev/null
curl --silent --show-error --fail --max-time 20 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/app/" >/dev/null
curl --silent --show-error --fail --max-time 20 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/app/sign/register/" >/dev/null
curl --silent --show-error --fail --max-time 20 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/api/home/main/init" >/dev/null

redirect_code="$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 20 --resolve "${DOMAIN}:80:127.0.0.1" "http://${DOMAIN}/")"
test "${redirect_code}" = '301'

openssl x509 -checkend 2592000 -noout -in "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" >/dev/null

table_count="$(mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='lhqb'")"
test "${table_count}" -ge 73
restore_db_count="$(mysql -Nse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='lhqb_restore_test'")"
test "${restore_db_count}" = '0'

database_backup="$(find /data/lhqb/backups/automatic/database -type f -name 'lhqb-*.sql.gz' -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"
files_backup="$(find /data/lhqb/backups/automatic/files -type f -name 'shared-*.tar.gz' -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"
gzip -t "${database_backup}"
tar -tzf "${files_backup}" >/dev/null

/usr/local/sbin/lhqb-health-check
tail -n 20 /data/lhqb/logs/application/health-check.log | grep -q 'HEALTH_CHECK=PASS'

echo 'FINAL_ACCEPTANCE=PASS'
echo "CURRENT_RELEASE=${CURRENT}"
echo "DATABASE_TABLES=${table_count}"
echo "DATABASE_BACKUP=${database_backup}"
echo "FILES_BACKUP=${files_backup}"
echo "HTTPS_ROOT=PASS"
echo "H5_APP=PASS"
echo "API_INIT=PASS"
echo "HTTP_REDIRECT=PASS"
echo "TLS_30_DAYS=PASS"
