#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="/data/lhqb"
BACKUP_ROOT="${APP_ROOT}/backups/automatic"
DATABASE_DIR="${BACKUP_ROOT}/database"
FILES_DIR="${BACKUP_ROOT}/files"
LOG_FILE="${APP_ROOT}/logs/application/backup.log"
DATABASE_NAME="lhqb"
DATABASE_CONFIG="${APP_ROOT}/shared/data/conf/database.php"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DATABASE_ARCHIVE="${DATABASE_DIR}/${DATABASE_NAME}-${STAMP}.sql.gz"
FILES_ARCHIVE="${FILES_DIR}/shared-${STAMP}.tar.gz"

mkdir -p "${DATABASE_DIR}" "${FILES_DIR}" "$(dirname "${LOG_FILE}")"
touch "${LOG_FILE}"
chmod 600 "${LOG_FILE}"

exec >>"${LOG_FILE}" 2>&1

echo "[$(date --iso-8601=seconds)] backup started"

if [ ! -f "${DATABASE_CONFIG}" ]; then
  echo "database config is missing: ${DATABASE_CONFIG}" >&2
  exit 1
fi

db_value() {
  php -r '$config = include $argv[1]; echo $config[$argv[2]] ?? "";' "${DATABASE_CONFIG}" "$1"
}

DB_HOST="$(db_value hostname)"
DB_PORT="$(db_value hostport)"
DB_USER="$(db_value username)"
DATABASE_NAME="$(db_value database)"
MYSQL_PWD="$(db_value password)"
export MYSQL_PWD

mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --user="${DB_USER}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --default-character-set=utf8mb4 \
  "${DATABASE_NAME}" | gzip -9 >"${DATABASE_ARCHIVE}"
unset MYSQL_PWD

gzip -t "${DATABASE_ARCHIVE}"
test -s "${DATABASE_ARCHIVE}"

backup_paths=(shared/data/conf shared/public/upload)
for optional_path in shared/python/news-crawler.env shared/python/trading.env; do
  if [ -e "${APP_ROOT}/${optional_path}" ]; then
    backup_paths+=("${optional_path}")
  fi
done
tar -C "${APP_ROOT}" -czf "${FILES_ARCHIVE}" "${backup_paths[@]}"

tar -tzf "${FILES_ARCHIVE}" >/dev/null
test -s "${FILES_ARCHIVE}"

chmod 600 "${DATABASE_ARCHIVE}" "${FILES_ARCHIVE}"

find "${DATABASE_DIR}" -type f -name '*.sql.gz' -mtime "+${RETENTION_DAYS}" -delete
find "${FILES_DIR}" -type f -name '*.tar.gz' -mtime "+${RETENTION_DAYS}" -delete

echo "[$(date --iso-8601=seconds)] backup completed database=$(basename "${DATABASE_ARCHIVE}") files=$(basename "${FILES_ARCHIVE}")"
