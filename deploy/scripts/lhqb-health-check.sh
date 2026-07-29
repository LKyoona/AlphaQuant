#!/usr/bin/env bash
set -uo pipefail

if [ -f /etc/lhqb/deploy.env ]; then
  # shellcheck disable=SC1091
  source /etc/lhqb/deploy.env
fi

LOG_FILE="/data/lhqb/logs/application/health-check.log"
DOMAIN="${LHQB_DOMAIN:-neuranet.site}"
FAILED=0

mkdir -p "$(dirname "${LOG_FILE}")"
touch "${LOG_FILE}"
chmod 640 "${LOG_FILE}"
exec >>"${LOG_FILE}" 2>&1

check_service() {
  local service="$1"
  if systemctl is-active --quiet "${service}"; then
    echo "OK service=${service}"
  else
    echo "FAIL service=${service}"
    FAILED=1
  fi
}

echo "[$(date --iso-8601=seconds)] health check started"
check_service nginx
check_service php8.1-fpm
check_service mysql
check_service redis-server
check_service lhqb-news-crawler.timer
check_service lhqb-backup.timer
check_service lhqb-smtp-mss.service

if curl --silent --show-error --fail --max-time 15 \
  --resolve "${DOMAIN}:443:127.0.0.1" \
  "https://${DOMAIN}/api/home/main/init" >/dev/null; then
  echo "OK endpoint=https://${DOMAIN}/api/home/main/init"
else
  echo "FAIL endpoint=https://${DOMAIN}/api/home/main/init"
  FAILED=1
fi

disk_usage="$(df --output=pcent /data/lhqb | tail -1 | tr -dc '0-9')"
if [ "${disk_usage}" -lt 85 ]; then
  echo "OK disk_usage=${disk_usage}%"
else
  echo "FAIL disk_usage=${disk_usage}% threshold=85%"
  FAILED=1
fi

if [ "${FAILED}" -eq 0 ]; then
  echo "[$(date --iso-8601=seconds)] HEALTH_CHECK=PASS"
else
  echo "[$(date --iso-8601=seconds)] HEALTH_CHECK=FAIL"
fi

exit "${FAILED}"
