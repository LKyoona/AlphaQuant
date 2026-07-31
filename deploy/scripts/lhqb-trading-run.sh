#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="/data/lhqb"
ENV_FILE="${TRADING_ENV_FILE:-${APP_ROOT}/shared/python/trading.env}"
PYTHON_BIN="${TRADING_PYTHON:-${APP_ROOT}/shared/python/venvs/market/bin/python}"
LOG_DIR="${APP_ROOT}/logs/trading"
LOCK_FILE="/run/lock/lhqb-trading.lock"

mkdir -p "${LOG_DIR}"

if [ ! -f "${ENV_FILE}" ]; then
  echo "交易配置不存在: ${ENV_FILE}" >&2
  exit 1
fi

if [ ! -x "${PYTHON_BIN}" ]; then
  echo "交易 Python 环境不存在: ${PYTHON_BIN}" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

# PyMySQL 由 Ubuntu 软件源安装，行情虚拟环境需要显式读取系统包目录。
export PYTHONPATH="/usr/lib/python3/dist-packages:${APP_ROOT}/current/python${PYTHONPATH:+:${PYTHONPATH}}"

if [ "${TRADING_EXECUTION_ENABLED:-false}" != "true" ]; then
  echo "正式交易开关未开启: TRADING_EXECUTION_ENABLED=true" >&2
  exit 1
fi

exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
  echo "上一轮交易任务仍在运行，本轮跳过"
  exit 0
fi

cd "${APP_ROOT}/current/python"
exec "${PYTHON_BIN}" -m trading.trading_engine --live
